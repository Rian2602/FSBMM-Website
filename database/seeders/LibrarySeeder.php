<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Eresource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            [
                'title' => 'AD/ART Federasi',
                'description' => 'Anggaran Dasar dan Anggaran Rumah Tangga FSBMM hasil Kongres.',
            ],
            [
                'title' => 'Leaflet Keselamatan Kerja',
                'description' => 'Panduan singkat K3 untuk pekerja lantai produksi.',
            ],
            [
                'title' => 'Template Surat Kuasa',
                'description' => 'Format surat kuasa untuk perwakilan serikat dalam perundingan.',
            ],
            [
                'title' => 'Modul Organisasi Dasar',
                'description' => 'Materi pendidikan dasar bagi calon pengurus SBA.',
            ],
        ];

        foreach ($resources as $resource) {
            $path = 'eresources/' . Str::slug($resource['title']) . '.pdf';

            Eresource::firstOrCreate(
                ['slug' => Str::slug($resource['title'])],
                [
                    'title' => $resource['title'],
                    'description' => $resource['description'],
                    'file_path' => $path,
                    'is_published' => true,
                    'downloads_count' => 0,
                ],
            );

            // Placeholder demo file so the signed download works end-to-end.
            // Replace by re-uploading via the admin panel (storage is gitignored).
            if (! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, self::placeholderPdf($resource['title']));
            }
        }

        $courses = [
            ['title' => 'Keselamatan Kerja di Pabrik', 'level' => 'dasar'],
            ['title' => 'Dasar-Dasar Perundingan Bersama', 'level' => 'dasar'],
            ['title' => 'Kepemimpinan Serikat Pekerja', 'level' => 'menengah'],
        ];

        foreach ($courses as $course) {
            Course::firstOrCreate(
                ['slug' => Str::slug($course['title'])],
                [
                    'title' => $course['title'],
                    'level' => $course['level'],
                    'description' => 'Pelatihan untuk pengurus dan anggota SBA di lingkungan FSBMM. Materi lengkap beserta evaluasi akan tersedia pada tahap berikutnya.',
                    'is_published' => true,
                ],
            );
        }
    }

    private static function placeholderPdf(string $title): string
    {
        $text = 'PLACEHOLDER — ' . $title;

        return "%PDF-1.4\n"
            . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            . '4 0 obj<</Length ' . strlen($text) . ">>stream\n"
            . 'BT /F1 24 Tf 72 720 Td (' . $text . ") Tj ET\n"
            . "endstream\nendobj\n"
            . "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            . "trailer<</Root 1 0 R>>\n%%EOF";
    }
}
