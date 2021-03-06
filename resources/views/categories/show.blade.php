@extends('layouts.app')

@section('title', $category->name )

@section('content')
			<div class="mt-3">
			    <img src="{{$category->icon}}" class="rounded float-left mr-3 w-25"  alt="{{ $category->name }}">
			    <h1>{{ $category->name }}</h1>
			    <a href="/categories">Category</a>
			    @can('update', $category)
			        <a href="/categories/{{$category->slug}}/edit">
			            {{ __('Edit') }}
			        </a>
			    @endcan
			</div>
		    <hr class="w-100 float-left">
		    <h2 class="float-left w-100">Posts</h2>
	@foreach($category->posts as $post)
		<a href="/posts/{{$post->slug}}">
		    <img src="{{$post->header_image}}" class="rounded mb-2" >
		    <h1>{{ $post->title }}</h1>
		    <p class="text-muted">{{ $post->excerpt }}</p>
		    <hr>
		</a>
	@endforeach
@endsection
