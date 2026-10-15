#!/usr/bin/env bash
# It. 42b — make staging-provision: crea o actualiza la infraestructura de
# staging con CloudFormation (deploy/aws/staging.yml) y genera los secretos que
# no escribe nadie (deploy/aws/init-secrets.sh). Se puede repetir.
#
# Corre en tu equipo, con el perfil de AWS del Makefile (aws login --profile …).
# Borrar todo: aws cloudformation delete-stack --stack-name govtrace-staging
# (los dos buckets quedan: guardan evidencias y respaldos).
set -euo pipefail
cd "$(dirname "$0")/../.."
STACK=${STACK:-govtrace-staging}
AMI_PARAMETER=/aws/service/canonical/ubuntu/server/24.04/stable/current/arm64/hvm/ebs-gp3/ami-id

# La AMI se fija la primera vez. Una más nueva reemplazaría la máquina, y con
# ella su base de datos.
ami=$(aws cloudformation describe-stacks --stack-name "$STACK" \
    --query "Stacks[0].Parameters[?ParameterKey=='ImageId'].ParameterValue" --output text 2>/dev/null || true)
if [ -z "$ami" ] || [ "$ami" = None ]; then
    ami=$(aws ssm get-parameter --name "$AMI_PARAMETER" --query Parameter.Value --output text)
    echo "La pila es nueva: Ubuntu 24.04 ARM, $ami"
fi

overrides=("ImageId=$ami")
[ -n "${STAGING_INSTANCE_TYPE:-}" ] && overrides+=("InstanceType=$STAGING_INSTANCE_TYPE")
# It. 61 (FinOps): el horario (por defecto se apaga a las 21:00 y se enciende a las 8:00,
# hora de Colombia), el presupuesto del mes y a quién le llegan sus alertas. Sin estas
# variables, la pila conserva lo que ya tenía.
[ -n "${STAGING_SLEEP:-}" ] && overrides+=("SleepAtNight=$STAGING_SLEEP")
[ -n "${STAGING_STOP_AT:-}" ] && overrides+=("StopAt=$STAGING_STOP_AT")
[ -n "${STAGING_START_AT:-}" ] && overrides+=("StartAt=$STAGING_START_AT")
[ -n "${STAGING_ALERT_EMAIL:-}" ] && overrides+=("AlertEmail=$STAGING_ALERT_EMAIL")
[ -n "${STAGING_BUDGET_USD:-}" ] && overrides+=("MonthlyBudgetUsd=$STAGING_BUDGET_USD")

aws cloudformation deploy --stack-name "$STACK" --template-file deploy/aws/staging.yml \
    --capabilities CAPABILITY_IAM --no-fail-on-empty-changeset \
    --parameter-overrides "${overrides[@]}" \
    --tags Project=govtrace Environment=staging

aws cloudformation describe-stacks --stack-name "$STACK" --query 'Stacks[0].Outputs' --output table
bash deploy/aws/init-secrets.sh
