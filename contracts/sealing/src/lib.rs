#![no_std]
//! GovTrace — Smart Contract de sellado en Soroban (US-020a · R-BLK-02,
//! R-BLK-03, R-SA-01).
//!
//! Registra la raíz de Merkle de cada reporte (R-BLK-05) junto a la
//! referencia de su obra, con la hora y el número del ledger en que se
//! selló. La hora la pone la red, no quien sella.
//!
//! - Solo la cuenta selladora, fijada al desplegar, puede sellar.
//! - Una raíz se sella una sola vez ("Hash ya registrado").
//! - No hay ninguna función para modificar ni borrar un sello, ni para
//!   reemplazar el código del contrato (sin `upgrade`): lo desplegado es
//!   inmutable. Cambiar la cuenta selladora es desplegar otro contrato; el
//!   verificador consulta todas las direcciones históricas (it. 23).

use soroban_sdk::{
    contract, contracterror, contractevent, contractimpl, contracttype, Address, BytesN, Env,
};

/// Lo que queda registrado por cada raíz de Merkle sellada.
#[contracttype]
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct Seal {
    /// SHA-256 de «organización:ficha» (R-BLK-02): única, sin datos legibles.
    pub worksite: BytesN<32>,
    /// Hora del ledger, en segundos Unix.
    pub sealed_at: u64,
    /// Número del ledger que incluyó el sello.
    pub ledger: u32,
}

#[contracttype]
enum DataKey {
    Sealer,
    Seal(BytesN<32>),
}

#[contracterror]
#[derive(Copy, Clone, Debug, Eq, PartialEq, PartialOrd, Ord)]
#[repr(u32)]
pub enum SealingError {
    /// "Hash ya registrado".
    HashAlreadyRegistered = 1,
}

/// Aviso público de cada sello, para quien indexe la red.
#[contractevent]
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct Sealed {
    #[topic]
    pub root: BytesN<32>,
    pub worksite: BytesN<32>,
    pub sealed_at: u64,
    pub ledger: u32,
}

#[contract]
pub struct SealingContract;

#[contractimpl]
impl SealingContract {
    /// Fija, de una vez y para siempre, la cuenta que puede sellar.
    pub fn __constructor(env: Env, sealer: Address) {
        env.storage().instance().set(&DataKey::Sealer, &sealer);
    }

    pub fn seal(env: Env, worksite: BytesN<32>, root: BytesN<32>) -> Result<Seal, SealingError> {
        let sealer: Address = env
            .storage()
            .instance()
            .get(&DataKey::Sealer)
            .expect("el constructor fija la cuenta selladora");
        sealer.require_auth();

        let key = DataKey::Seal(root.clone());

        if env.storage().persistent().has(&key) {
            return Err(SealingError::HashAlreadyRegistered);
        }

        let seal = Seal {
            worksite: worksite.clone(),
            sealed_at: env.ledger().timestamp(),
            ledger: env.ledger().sequence(),
        };

        // Persistente y con la vigencia máxima que permite la red. Un sello
        // archivado igual no se pierde: la red lo restaura (Protocolo 23).
        // La vigencia de la instancia y del código no se toca aquí: la
        // extiende la tesorería (D12), para que la cuenta que paga cada
        // sello solo pague la renta de ese sello.
        let max_ttl = env.storage().max_ttl();
        env.storage().persistent().set(&key, &seal);
        env.storage()
            .persistent()
            .extend_ttl(&key, max_ttl, max_ttl);

        Sealed {
            root,
            worksite,
            sealed_at: seal.sealed_at,
            ledger: seal.ledger,
        }
        .publish(&env);

        Ok(seal)
    }

    pub fn get_seal(env: Env, root: BytesN<32>) -> Option<Seal> {
        env.storage().persistent().get(&DataKey::Seal(root))
    }
}

#[cfg(test)]
mod test;
