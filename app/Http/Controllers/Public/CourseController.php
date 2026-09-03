<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Course;

class CourseController extends Controller
{
    public function index()
    {
        return view('public.courses.index', [
            'courses' => Course::published()
                ->orderByRaw("CASE level WHEN 'dasar' THEN 1 WHEN 'menengah' THEN 2 ELSE 3 END")
                ->orderBy('title')
                ->get(),
        ]);
    }
}
