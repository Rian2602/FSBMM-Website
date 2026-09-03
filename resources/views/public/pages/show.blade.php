@extends('layouts.public')

@section('title', $page->meta_title ?: $page->title)

@section('meta')
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
@endsection

@section('content')
    @foreach ($page->blocks as $block)
        {!! app(\App\Support\PageBlockRenderer::class)->render($block) !!}
    @endforeach
@endsection
