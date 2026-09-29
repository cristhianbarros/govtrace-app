// Declarative pipeline: Build (Docker) -> Format Check (Pint) -> Test Backend (Pest) -> Test Frontend (Vitest)
// -> Test Contract (Soroban: rustfmt, clippy, cargo test and the exact WASM interface)
// -> Test Stellar (sealing against a local standalone network with the contract deployed)
// -> Backup & Restore (hourly backups and the restore drill, R-BCK)
// -> Secrets Check (no Stellar secret key in the repo or its history)
// -> Smoke Testnet (only when building a release tag; keys injected from Jenkins credentials, D11).
// It drives the same Makefile targets developers use, so CI and local runs cannot drift.
// The Jenkins agent only needs Docker (with the compose plugin) and make.
pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
    }

    environment {
        COMPOSE_PROJECT_NAME = "govtrace-ci-${env.BUILD_NUMBER}"
        // Shell env wins over .env.docker: CI gets its own ports, network and
        // subnet so it never collides with a developer stack on the same agent.
        LOCAL_IP       = '127.0.0.1'
        HTTP_PORT      = '18080'
        PG_PORT        = '15432'
        DOCKER_NETWORK = 'govtrace_ci_net'
        DOCKER_SUBNET  = '172.29.250.0/24'
        STELLAR_PORT   = '18100'
    }

    stages {
        stage('Build') {
            steps {
                script {
                    env.PUID = sh(script: 'id -u', returnStdout: true).trim()
                    env.PGID = sh(script: 'id -g', returnStdout: true).trim()
                }
                // Creates .env / .env.docker from the examples, builds the image,
                // installs composer + npm deps, migrates and builds assets.
                sh 'make setup'
            }
        }

        stage('Format Check') {
            steps {
                sh 'make lint'
                // Every Gherkin scenario has a test named after it (it. 36).
                sh 'make trace-check'
            }
        }

        stage('Test Backend') {
            steps {
                sh 'make test'
            }
        }

        stage('Test Frontend') {
            steps {
                sh 'make test-front'
            }
        }

        stage('E2E') {
            steps {
                // R-TST-03 (it. 30): US-018 in a real Chromium (Playwright) against the
                // stack make setup brought up — the phone loses its signal, the report
                // waits in IndexedDB, the app opens offline, and it is sent on its own.
                sh 'make e2e'
            }
        }

        stage('Test Contract') {
            steps {
                // Sealing Smart Contract (it. 12). Runs in the Rust + Stellar CLI
                // container; needs no Stellar network.
                sh 'make contract-test'
            }
        }

        stage('Test Stellar') {
            steps {
                // Sealing end to end (it. 13): a fresh local standalone network,
                // the contract deployed on it, and the "stellar" test group
                // (fee bump paid by the sponsor, duplicates, report -> Sellada).
                // Then the independent verifier (it. 23, US-026) on an evidence
                // GovTrace published, isolated with the Stellar node alone.
                sh 'make stellar-up && make contract-deploy && make test-stellar && make verify-check'
            }
        }

        stage('Backup & Restore') {
            steps {
                // R-BCK-01..05 (it. 35): a backup of every database and the evidence
                // bucket; unchanged files are hard-linked, old copies expire at 30
                // days, and the restore drill restores everything into an empty
                // PostgreSQL and a test bucket, checking each evidence's SHA-256.
                sh 'make backup-check'
            }
        }

        stage('Secrets Check') {
            steps {
                // R-BLK-04 / D11: no Stellar secret key in any tracked file, in the
                // whole git history, or in the env examples.
                sh 'make secrets-check'
            }
        }

        stage('Monitoring Check') {
            // US-044-MON: before each release, the external monitor's real
            // configuration (Gatus) alerts by email and webhook after the
            // threshold, and stays quiet for a short blip. ~40 s, no secrets.
            when { buildingTag() }
            steps {
                sh 'make monitoring-check'
            }
        }

        stage('Smoke Testnet') {
            // R-TST-01: before each release. The keys are never in the repo:
            // Jenkins injects them from its credentials store (D11) into a
            // .env.testnet that only lives during this stage.
            when { buildingTag() }
            steps {
                withCredentials([
                    string(credentialsId: 'stellar-testnet-contract-id', variable: 'STELLAR_TESTNET_CONTRACT_ID'),
                    string(credentialsId: 'stellar-testnet-sealer-secret', variable: 'STELLAR_TESTNET_SEALER_SECRET'),
                    string(credentialsId: 'stellar-testnet-sponsor-secret', variable: 'STELLAR_TESTNET_SPONSOR_SECRET'),
                ]) {
                    sh '''
                        umask 077
                        {
                          echo 'STELLAR_RPC_URL=https://soroban-testnet.stellar.org'
                          echo 'STELLAR_NETWORK_PASSPHRASE="Test SDF Network ; September 2015"'
                          echo "STELLAR_SEALING_CONTRACT_ID=$STELLAR_TESTNET_CONTRACT_ID"
                          echo "STELLAR_SEALER_SECRET=$STELLAR_TESTNET_SEALER_SECRET"
                          echo "STELLAR_SPONSOR_SECRET=$STELLAR_TESTNET_SPONSOR_SECRET"
                        } > .env.testnet
                        make smoke-testnet
                    '''
                }
            }
        }
    }

    post {
        always {
            sh 'rm -f .env.testnet'
            sh "docker compose --env-file .env.docker --profile '*' down -v --remove-orphans || true"
        }
    }
}
