<?php

namespace Tests\Feature;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_app_timezone_is_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_carbon_now_uses_configured_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', now()->timezoneName);
    }

    public function test_s3_flysystem_adapter_is_installed(): void
    {
        // Production swaps the local/public disks to the S3 driver via
        // FILESYSTEM_*_DRIVER=s3 (Vercel container runtime). The Flysystem AWS
        // adapter must be present, otherwise every storage op throws
        // "League\Flysystem\AwsS3V3\PortableVisibilityConverter not found"
        // (FilesystemManager.php:249) and generate-report/exports/uploads all
        // 500. Guarded by config/filesystems.php's $s3Ready check.
        $this->assertTrue(class_exists(PortableVisibilityConverter::class));
        $this->assertTrue(class_exists(S3Client::class));
    }

    public function test_swappable_disks_default_to_local_in_tests(): void
    {
        // Tests keep the local disks; only production opts into the S3 swap.
        $this->assertSame('local', config('filesystems.disks.local.driver'));
        $this->assertSame('local', config('filesystems.disks.public.driver'));
        $this->assertSame('s3', config('filesystems.disks.s3.driver'));
    }

    public function test_s3_disk_shape_allows_classic_aws_without_endpoint(): void
    {
        // AWS_ENDPOINT is optional: classic AWS S3 resolves the endpoint from
        // AWS_DEFAULT_REGION; the guard in config/filesystems.php must NOT
        // require it (R2/Spaces set it via env only when used). The value is
        // null when unset locally, or '' when .env.example is copied (.env has
        // AWS_ENDPOINT=), so both shapes must be tolerated.
        $endpoint = config('filesystems.disks.s3.endpoint');
        $this->assertTrue($endpoint === null || $endpoint === '');

        $this->assertFalse((bool) config('filesystems.disks.s3.use_path_style_endpoint'));
    }
}
