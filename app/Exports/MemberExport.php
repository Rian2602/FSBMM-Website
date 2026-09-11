<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

class MemberExport extends ReportExport
{
    protected static function columns(array $filters = []): array
    {
        return [
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
    }

    protected static function filenamePrefix(): string
    {
        return 'anggota';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $query = Member::query()->where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['department'])) {
            $query->where('department', 'like', '%' . $filters['department'] . '%');
        }
        if (! empty($filters['position'])) {
            $query->where('position', 'like', '%' . $filters['position'] . '%');
        }
        if (! empty($filters['education'])) {
            $query->where('education', 'like', '%' . $filters['education'] . '%');
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

    protected static function row($model, array $filters = []): array
    {
        $member = $model;

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
