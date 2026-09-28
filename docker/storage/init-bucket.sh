#!/usr/bin/env bash
# Runs automatically on LocalStack boot (mounted at
# /etc/localstack/init/ready.d/, docker-compose.yml "storage" service).
# Creates the evidence bucket so config/filesystems.php's "evidencias" disk
# has somewhere to write from the very first request.
set -euo pipefail

awslocal s3 mb "s3://${EVIDENCE_BUCKET:-evidencias}" --region "${DEFAULT_REGION:-us-east-1}" 2>&1 \
  | grep -v 'BucketAlreadyOwnedByYou\|BucketAlreadyExists' || true
