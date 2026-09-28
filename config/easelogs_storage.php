<?php

/**
 * EaseLogs persistent file storage — installation configuration registry.
 *
 * Application code references a target name such as `local_persistent`.
 * Connection details live here, not in customer-entered database secrets.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default configured target
    |--------------------------------------------------------------------------
    |
    | Used when resolving the installation default persistent provider.
    |
    */

    'default_target' => env('EASELOGS_STORAGE_DEFAULT_TARGET', 'local_persistent'),

    /*
    |--------------------------------------------------------------------------
    | Configured storage targets
    |--------------------------------------------------------------------------
    |
    | Each key is a stable config_target name referenced by application code.
    | Driver-specific options stay here.
    | Laravel filesystem disk names must not appear in domain/business logic.
    |
    */

    'targets' => [

        'local_persistent' => [
            'driver' => 'local',
            'root' => env('EASELOGS_LOCAL_PERSISTENT_ROOT')
                ?: storage_path('app/persistent'),
            /*
             * Mount / root readiness for assertRootReady() only.
             * - none: root must exist as a readable/writable directory
             * - marker: also require mount_marker file under the root (recommended for NAS)
             * - nonempty: reject an empty directory (helps detect vanished mounts)
             *
             * Phase 1 does not perform NAS protocol verification. A writable
             * directory alone does not prove a remote filesystem is mounted.
             * External/mount roots are never auto-created on write.
             */
            'mount_check' => env('EASELOGS_LOCAL_PERSISTENT_MOUNT_CHECK', 'none'),
            'mount_marker' => env('EASELOGS_LOCAL_PERSISTENT_MOUNT_MARKER', '.easelogs-storage-ok'),
        ],

    ],

];
