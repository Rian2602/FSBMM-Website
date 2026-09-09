<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemberExport
{
    public const COLUMNS = [
        'name' => 'Nama',
        'nik' => 'NIK',
        'gender' => 'Jenis Kelamin',
        'birthplace' => 'Tempat Lahir',
        'birthdate' => 'Tanggal Lahir',
        'address' => 'Alamat',
        'department' => 'Departemen',
        'position' => 'Jabatan',
        'basic_salary' => 'Upah Dasar',
        'join_date' => 'Tanggal Bergabung',
        'education' => 'Pendidikan',
        'status' => 'Status',
    ];

    public static function streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = sys_get_temp_dir().'/fsbmm_'.Str::random(8).'.'.$format;
        $query = static::scopedQuery($organizationId, $filters);

        $format === 'xlsx' ? static::writeXlsx($query, $path) : static::writeCsv($query, $path);

        return response()
            ->download($path, sprintf('anggota-%s-%s.%s', $organizationId, now()->format('Y-m-d'), $format))
            ->deleteFileAfterSend(true);
    }

    private static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $query = Member::query()->where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['department'])) {
            $query->where('department', 'like', '%'.$filters['department'].'%');
        }
        if (! empty($filters['position'])) {
            $query->where('position', 'like', '%'.$filters['position'].'%');
        }
        if (! empty($filters['education'])) {
            $query->where('education', 'like', '%'.$filters['education'].'%');
        }
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (! empty($filters['join_date_start'])) {
            $query->whereDate('join_date', '>=', $filters['join_date_start']);
        }
        if (! empty($filters['join_date_end'])) {
            $query->whereDate('join_date', '<=', $filters['join_date_end']);
        }

        return $query->orderBy('id');
    }

    private static function writeCsv(Builder $query, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_values(self::COLUMNS));
        static::rows($query)->each(function (Member $member) use ($handle): void {
            fputcsv($handle, static::row($member));
        });
        fclose($handle);
    }

    private static function writeXlsx(Builder $query, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(self::COLUMNS)));
        static::rows($query)->each(function (Member $member) use ($writer): void {
            $writer->addRow(Row::fromValues(static::row($member)));
        });
        $writer->close();
    }

    private static function rows(Builder $query): \Illuminate\Support\LazyCollection
    {
        return $query->cursor();
    }

    private static function row(Member $member): array
    {
        return [
            $member->name,
            $member->nik,
            match ($member->gender) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                default => $member->gender,
            },
            $member->birthplace,
            $member->birthdate?->format('Y-m-d'),
            $member->address,
            $member->department,
            $member->position,
            $member->basic_salary,
            $member->join_date?->format('Y-m-d'),
            $member->education,
            $member->status,
        ];
    }
}
