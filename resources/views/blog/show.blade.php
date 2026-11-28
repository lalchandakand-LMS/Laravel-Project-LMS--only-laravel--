@extends('layouts.app')
@section('title', $post->meta_title ?: $post->title)
@section('meta_description', $post->meta_description ?: $post->excerpt)
@section('meta_image', $post->thumbnail ? media_url($post->thumbnail) : '')
@section('meta_image_alt', $post->title)
@section('og_type', 'article')
@section('content')
    <?php fwrite(STDERR, 'BLOG_BUF before_show_partial='.ob_get_level()."\\n"); ?>
    @include('blog.partials.show-content')
    <?php fwrite(STDERR, 'BLOG_BUF after_show_partial='.ob_get_level()."\\n"); ?>
@endsection
