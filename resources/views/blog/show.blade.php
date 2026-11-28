@extends('layouts.app')
@section('title', $post->meta_title ?: $post->title)
@section('meta_description', $post->meta_description ?: ($post->excerpt ?: 'Read the latest learning article from Alom’s English Center.'))
@section('meta_image', $post->thumbnail ? media_url($post->thumbnail) : '')
@section('meta_image_alt', $post->title)
@section('og_type', 'article')
@section('content')
    @include('blog.partials.show-content')
@endsection
