<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::published()
            ->orderBy('name')
            ->get();

        return view('public.organizations.index', ['organizations' => $organizations]);
    }

    public function show(Organization $organization)
    {
        abort_unless($organization->is_published, 404);

        return view('public.organizations.show', ['organization' => $organization]);
    }
}
