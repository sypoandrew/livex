@extends('admin::layouts.main')

@section('content')

    <livex-management inline-template>


    <div class="flex pb-2 mb-4">
        <h2 class="flex-1 m-0 p-0">
		<a href="{{ route('admin.modules') }}" class="btn mr-4">&#171; Back</a>
		<span class="flex-1">Liv-Ex</span>
		</h2>
    </div>
    @include('admin::partials.alerts')
	<form action="{{ route('admin.modules.livex') }}" method="post" class="flex flex-wrap">
		@csrf
		<div class="card mt-4 w-full">
			<h3>Add images to Product Library</h3>
			
			
			<template>
			<div id="app">
				<vue-dropzone id="drop1" :options="dropOptions"></vue-dropzone>
			</div>
			</template>
			
			
			
		</div>
		
		<div class="card mt-4 p-4 w-full flex flex-wrap"><button type="submit" class="btn btn-secondary">Save</button> </div>
	</form>
		
		</livex-management>
@endsection
