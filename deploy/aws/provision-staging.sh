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

aws cloudformation deploy --stack-name "$STACK" --template-file deploy/aws/staging.yml \
    --capabilities CAPABILITY_IAM --no-fail-on-empty-changeset \
    --parameter-overrides "${overrides[@]}" \
    --tags Project=govtrace Environment=staging

aws cloudformation describe-stacks --stack-name "$STACK" --query 'Stacks[0].Outputs' --output table
bash deploy/aws/init-secrets.sh
