#!/usr/bin/env sh
# La testnet de Stellar (it. 14). Se incluye antes de stellar-common.sh en los
# scripts que hablan con testnet; no se ejecuta sola.
STELLAR_RPC_URL=https://soroban-testnet.stellar.org
STELLAR_NETWORK_PASSPHRASE="Test SDF Network ; September 2015"
STELLAR_FRIENDBOT_URL=https://friendbot.stellar.org
ENV_FILE=/workspace/.env.testnet
