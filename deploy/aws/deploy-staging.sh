#!/usr/bin/env bash
# It. 42b — make staging-deploy [REF=main]: despliega en la máquina de staging
# por SSM Run Command, sin SSH. La máquina trae la rama REF de GitHub y corre
# deploy/staging-up.sh. Espera a que termine y muestra su salida.
set -euo pipefail
STACK=${STACK:-govtrace-staging}
REF=${REF:-main}
[[ $REF =~ ^[A-Za-z0-9._/-]+$ ]] || { echo "deploy-staging.sh: REF no es un nombre de rama: $REF" >&2; exit 2; }

instance=$(aws cloudformation describe-stacks --stack-name "$STACK" \
    --query "Stacks[0].Outputs[?OutputKey=='InstanceId'].OutputValue" --output text)

# La primera vez, la máquina tarda unos minutos en registrarse en SSM.
for _ in $(seq 1 60); do
    status=$(aws ssm describe-instance-information --filters "Key=InstanceIds,Values=$instance" \
        --query 'InstanceInformationList[0].PingStatus' --output text 2>/dev/null || true)
    [ "$status" = Online ] && break
    echo "Esperando a que $instance se registre en SSM (${status:-sin respuesta})…"
    sleep 10
done
[ "$status" = Online ] || { echo "deploy-staging.sh: $instance no aparece en SSM." >&2; exit 1; }

commands=$(python3 -I -c '
import json, sys
ref = sys.argv[1]
print(json.dumps({
    "commands": [
        # sh, que en Ubuntu es dash: sin pipefail.
        "set -eu",
        # Que cloud-init termine de instalar Docker la primera vez.
        "cloud-init status --wait >/dev/null || true",
        "cd /opt/govtrace",
        "git fetch --quiet --prune origin",
        f"git checkout --quiet --force -B staging origin/{ref}",
        "git log -1 --format=\"Desplegando %h: %s\"",
        "deploy/staging-up.sh",
    ],
    "executionTimeout": ["3600"],
}))' "$REF")

command_id=$(aws ssm send-command --instance-ids "$instance" --document-name AWS-RunShellScript \
    --comment "make staging-deploy REF=$REF" --parameters "$commands" \
    --query Command.CommandId --output text)
echo "Desplegando $REF en $instance (comando $command_id). Construir la imagen tarda unos minutos…"

while true; do
    sleep 15
    status=$(aws ssm get-command-invocation --command-id "$command_id" --instance-id "$instance" \
        --query Status --output text 2>/dev/null || echo Pending)
    case $status in Pending | InProgress | Delayed) continue ;; esac
    break
done

aws ssm get-command-invocation --command-id "$command_id" --instance-id "$instance" \
    --query '[StandardOutputContent, StandardErrorContent]' --output text
[ "$status" = Success ] || { echo "deploy-staging.sh: el despliegue terminó en $status." >&2; exit 1; }
