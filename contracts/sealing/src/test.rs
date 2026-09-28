//! Traduce features/US-020a.feature (iteración 12, specs/PLAN.md): un test
//! por escenario, con el nombre del escenario, en el entorno de pruebas de
//! Soroban (sin red). La interfaz exacta del WASM compilado la revisa
//! además `check-interface.sh` (make contract-test).

extern crate std;

use super::{DataKey, Seal, SealingContract, SealingContractClient, SealingError};
use soroban_sdk::{
    testutils::{
        storage::Persistent as _, Address as _, AuthorizedFunction, AuthorizedInvocation, Ledger,
        MockAuth, MockAuthInvoke,
    },
    Address, BytesN, Env, IntoVal, Symbol, Val, Vec,
};

/// 32 bytes iguales: basta para distinguir raíces y obras en los tests.
fn bytes32(env: &Env, byte: u8) -> BytesN<32> {
    BytesN::from_array(env, &[byte; 32])
}

/// Antecedentes: el contrato desplegado con la cuenta selladora de GovTrace.
fn deploy(env: &Env) -> (Address, SealingContractClient<'_>) {
    let sealer = Address::generate(env);
    let contract_id = env.register(SealingContract, (&sealer,));

    (sealer, SealingContractClient::new(env, &contract_id))
}

#[test]
fn la_cuenta_selladora_registra_un_sello() {
    let env = Env::default();
    env.ledger().set_sequence_number(1200);
    env.ledger().set_timestamp(1_790_000_000);
    let (sealer, client) = deploy(&env);
    env.mock_all_auths();

    let worksite = bytes32(&env, 0xab);
    let root = bytes32(&env, 0x9f);
    client.seal(&worksite, &root);

    // R-BLK-03: la única autorización que pidió el contrato es la del sellador.
    assert_eq!(
        env.auths(),
        std::vec![(
            sealer.clone(),
            AuthorizedInvocation {
                function: AuthorizedFunction::Contract((
                    client.address.clone(),
                    Symbol::new(&env, "seal"),
                    (worksite.clone(), root.clone()).into_val(&env),
                )),
                sub_invocations: std::vec![],
            }
        )]
    );

    // R-BLK-02: la hora y el ledger los pone la red, no quien sella.
    assert_eq!(
        client.get_seal(&root),
        Some(Seal {
            worksite,
            sealed_at: 1_790_000_000,
            ledger: 1200
        })
    );
}

#[test]
fn una_cuenta_que_no_es_la_selladora_no_puede_sellar() {
    let env = Env::default();
    let (_sealer, client) = deploy(&env);
    let outsider = Address::generate(&env);
    let worksite = bytes32(&env, 0xab);
    let root = bytes32(&env, 0x11);

    // La cuenta externa firma su propia autorización; la del sellador no existe.
    let attempt = client
        .mock_auths(&[MockAuth {
            address: &outsider,
            invoke: &MockAuthInvoke {
                contract: &client.address,
                fn_name: "seal",
                args: (worksite.clone(), root.clone()).into_val(&env),
                sub_invokes: &[],
            },
        }])
        .try_seal(&worksite, &root);

    assert!(
        attempt.is_err(),
        "la red aceptó un sello sin la firma del sellador"
    );
    assert_eq!(client.get_seal(&root), None);
}

#[test]
fn un_hash_ya_registrado_no_se_puede_registrar_de_nuevo() {
    let env = Env::default();
    env.ledger().set_sequence_number(1200);
    env.ledger().set_timestamp(1_790_000_000);
    let (_sealer, client) = deploy(&env);
    env.mock_all_auths();

    let root = bytes32(&env, 0x9f);
    client.seal(&bytes32(&env, 0xab), &root);

    // Otra obra y otro momento: igual se rechaza.
    env.ledger().set_sequence_number(1320);
    env.ledger().set_timestamp(1_790_000_600);
    let again = client.try_seal(&bytes32(&env, 0xcd), &root);

    // "Hash ya registrado" es el error 1 del contrato; el servidor lo
    // traduce y descarta el duplicado en la it. 13.
    assert_eq!(again, Err(Ok(SealingError::HashAlreadyRegistered)));
    assert_eq!(
        client.get_seal(&root),
        Some(Seal {
            worksite: bytes32(&env, 0xab),
            sealed_at: 1_790_000_000,
            ledger: 1200
        })
    );
}

#[test]
fn nadie_puede_modificar_ni_borrar_un_sello_registrado() {
    let env = Env::default();
    let (_sealer, client) = deploy(&env);
    env.mock_all_auths();

    let root = bytes32(&env, 0x9f);
    let original = client.seal(&bytes32(&env, 0xab), &root);

    // R-SA-01: ni el Super Administrador ni nadie encuentra con qué cambiarlo.
    // Si una de estas funciones existiera y modificara o borrara el sello,
    // la llamada tendría éxito o el sello cambiaría. Un `upgrade` con un
    // hash inexistente falla igual que una función que no existe, así que su
    // ausencia la garantiza además scripts/check-interface.sh, que exige que
    // el WASM exporte exactamente __constructor, seal y get_seal.
    for function in [
        "update_seal",
        "set_seal",
        "delete_seal",
        "remove_seal",
        "upgrade",
        "set_sealer",
    ] {
        let args: Vec<Val> = (root.clone(),).into_val(&env);
        let attempt = env.try_invoke_contract::<Val, soroban_sdk::Error>(
            &client.address,
            &Symbol::new(&env, function),
            args,
        );

        assert!(
            attempt.is_err(),
            "el contrato expone la operación {function}"
        );
    }

    assert_eq!(client.get_seal(&root), Some(original));
}

// Técnico -----------------------------------------------------------------

#[test]
fn un_sello_queda_con_la_maxima_vigencia_que_permite_la_red() {
    let env = Env::default();
    let (_sealer, client) = deploy(&env);
    env.mock_all_auths();

    let root = bytes32(&env, 0x9f);
    client.seal(&bytes32(&env, 0xab), &root);

    // Almacenamiento persistente con el TTL al máximo: un sello archivado
    // igual no se pierde (la red lo restaura, Protocolo 23), pero así vive
    // lo más posible sin intervención.
    let ttl = env.as_contract(&client.address, || {
        env.storage()
            .persistent()
            .get_ttl(&DataKey::Seal(root.clone()))
    });

    assert_eq!(ttl, env.storage().max_ttl());
}
