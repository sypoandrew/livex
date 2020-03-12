@extends('admin::layouts.main')

@section('content')
    <div class="flex pb-2 mb-4">
        <h2 class="flex-1 m-0 p-0">Liv-Ex</h2>
    </div>
    @include('admin::partials.alerts')
	<form action="{{ route('admin.modules.livex') }}" method="post" class="flex flex-wrap">
		@csrf
		<div class="card mt-4 w-full">
			<h3>Liv-Ex API settings for Aero Commerce</h3>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">
			<label class="checkbox">
			<input type="checkbox" id="enabled" name="enabled" value="1">
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
		
@endsection
