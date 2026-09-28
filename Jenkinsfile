// Declarative pipeline: Build (Docker) -> Format Check (Pint) -> Test Backend (Pest) -> Test Frontend (Vitest)
// -> Test Contract (Soroban: rustfmt, clippy, cargo test and the exact WASM interface)
// -> Test Stellar (sealing against a local standalone network with the contract deployed).
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
                sh 'make stellar-up && make contract-deploy && make test-stellar'
            }
        }
    }

    post {
        always {
            sh "docker compose --env-file .env.docker --profile '*' down -v --remove-orphans || true"
        }
    }
}
