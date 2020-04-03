@extends('admin::layouts.main')

@section('content')
<!--
    <livex-management inline-template>
			-->


    <div class="flex pb-2 mb-4">
        <h2 class="flex-1 m-0 p-0">
		<a href="{{ route('admin.modules') }}" class="btn mr-4">&#171; Back</a>
		<span class="flex-1">Liv-Ex</span>
		</h2>
    </div>
    @include('admin::partials.alerts')
	
	@if(session()->has('message'))
		<div class="alert alert-success">
			{{ session()->get('message') }}
		</div>
	@endif
	
	<form action="{{ route('admin.modules.livex') }}" method="post" class="flex flex-wrap">
		@csrf
		<div class="card mt-4 w-full">
			<h3>Liv-Ex API settings for Aero Commerce</h3>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">
			<label class="checkbox">
			@if(setting('Livex.enabled'))
			<input type="checkbox" id="enabled" name="enabled" checked="checked" value="1">
			@else
			<input type="checkbox" id="enabled" name="enabled" value="1">
			@endif
			<span></span>
			</label>Enabled
			</label>
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Stock Threshold</label>
			<input type="text" id="stock_threshold" name="stock_threshold" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.stock_threshold') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Price Threshold</label>
			<input type="text" id="price_threshold" name="price_threshold" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.price_threshold') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Margin Markup %</label>
			<input type="text" id="margin_markup" name="margin_markup" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.margin_markup') }}">
			</div>
		</div>
		
		<div class="card mt-4 p-4 w-full flex flex-wrap"><button type="submit" class="btn btn-secondary">Save</button> </div>
	</form>
	
	<form action="{{ route('admin.modules.livex') }}" method="post" class="flex flex-wrap">
		@csrf
		<div class="card mt-4 w-full">
			<h3>Add images to Product Library</h3>
			<p>TBC</p>
			<!--
			<template>
			<div id="app">
				<vue-dropzone id="drop1" :options="dropOptions"></vue-dropzone>
			</div>
			</template>
			-->
			
			
		</div>
		
		<div class="card mt-4 p-4 w-full flex flex-wrap"><button type="submit" class="btn btn-secondary">Save</button> </div>
	</form>
	
	
	<form action="{{ route('admin.modules.livex.search_market') }}" method="get" class="flex flex-wrap mb-8">
		@csrf
		
		<div class="card mt-4 p-4 w-full flex flex-wrap">
			<h3 class="w-full">Liv-ex Search Market API</h3>
			<p><strong>NOTE:</strong> this is run via an automated routine every X minutes. Press the button below to run the routine manually.</p>
		</div>
		<div class="card mt-4 p-4 w-full flex flex-wrap">
			<button type="submit" class="btn btn-secondary">Process</button>
		</div>
	</form>
	
	<form action="{{ route('admin.modules.livex.placeholder_image') }}" method="get" class="flex flex-wrap mb-8">
		@csrf
		
		<div class="card mt-4 p-4 w-full flex flex-wrap">
			<h3 class="w-full">Create Placeholder Images</h3>
			<p><strong>NOTE:</strong> this is run via an automated routine every X minutes. Press the button below to run the routine manually.</p>
			<p><strong>NOTE:</strong> an email will be sent after the routine is complete, with any products still missing images reported on. This is usually due to missing colour, wine type data.</p>
		</div>
		<div class="card mt-4 p-4 w-full flex flex-wrap">
			<button type="submit" class="btn btn-secondary">Process</button>
		</div>
	</form>
	
	
		
			<!--
		</livex-management>
			-->
@endsection
