# Engine Backup and Recovery

This runbook governs the persistent single-node SQLite Engine described in `deploy/PRODUCTION.md`. It does not make SQLite a clustered datastore or provide automatic failover.

## Recovery objectives

Operators must set approved values for:

- recovery point objective (RPO): maximum acceptable committed-data loss;
- recovery time objective (RTO): maximum time from incident declaration to verified service restoration;
- backup frequency, which must be shorter than the RPO;
- retention periods and legal or policy holds; and
- restore-drill frequency, which must be sufficient to demonstrate the RTO.

No example value is an implicit production commitment. Record the approved objectives in the deployment platform and alert when the newest verified off-site backup is older than the RPO.

## Backup contract

Set:

- `SQUIRRELFORGE_ENGINE_DB` to the live SQLite database;
- `SQUIRRELFORGE_BACKUP_PATH` to a new local staging path; and
- optionally `SQUIRRELFORGE_BACKUP_MANIFEST_PATH`.

Run `composer backup:engine`. The command uses SQLite `VACUUM INTO` to create a consistent snapshot that includes committed WAL state without copying live database sidecar files. It refuses to overwrite an existing backup or manifest, runs `PRAGMA integrity_check`, and emits a manifest containing the byte length, SHA-256 digest, SQLite user version, and creation time.

Immediately move the backup and manifest as one immutable unit to encrypted, access-controlled storage in a separate failure domain. The transfer mechanism must verify the manifest checksum after upload. Do not log database content, credentials, session material, or secret-provider data. Restrict backup read and restore permissions separately from ordinary runtime access.

The scheduler and object-storage mechanism are platform responsibilities. Their success signal must require all of:

1. backup command success;
2. off-site upload success;
3. checksum verification from the stored object;
4. retention-policy application; and
5. freshness monitoring against the RPO.

## Independent verification

Download both artifacts into an isolated operator environment, set `SQUIRRELFORGE_BACKUP_PATH` and the optional manifest path, then run `composer verify:backup`. A backup is usable only when its filename, length, checksum, manifest version, and SQLite integrity all pass.

Verification on the same storage and host that created the backup does not demonstrate disaster recovery.

## Restore procedure

1. Declare the incident and preserve the failed database plus WAL/SHM sidecars for investigation.
2. Stop all Engine writers. Never restore over a database used by a running process.
3. Select the newest independently verified backup that satisfies incident and retention constraints.
4. Download the backup and manifest into an isolated staging directory.
5. Set `SQUIRRELFORGE_BACKUP_PATH`, optional `SQUIRRELFORGE_BACKUP_MANIFEST_PATH`, `SQUIRRELFORGE_RESTORE_DB` to a nonexistent target, and optionally `SQUIRRELFORGE_RESTORE_RECEIPT_PATH`.
6. Run `composer restore:engine`. It re-verifies the manifest and database, copies to a temporary file, and atomically installs the restored database with owner-only permissions. Existing database or sidecar paths are rejected.
7. Point `SQUIRRELFORGE_ENGINE_DB` at the restored path and run `composer runtime:preflight`.
8. Start one Engine instance, verify provider readiness, and run the production smoke test with a dedicated short-lived identity.
9. Confirm expected execution, authorization, security-event, and telemetry records before reopening traffic.
10. Preserve the restore receipt and incident timeline according to audit policy.

Do not merge the failed database into the restored copy during incident recovery. Any later forensic reconciliation requires a separately reviewed migration.

## Restore drill

Run a drill in an isolated environment on the required schedule:

1. retrieve a backup through the real off-site path;
2. verify and restore it to an empty target;
3. run runtime preflight and the smoke test;
4. measure backup age, retrieval time, restoration time, and total recovery time;
5. verify that the measured data-loss window satisfies RPO and total time satisfies RTO; and
6. retain a receipt identifying the backup digest and results without sensitive database content.

A backup job is not healthy merely because it produced files. Production readiness requires a recent successful end-to-end restore drill.
