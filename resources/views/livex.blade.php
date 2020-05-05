@extends('admin::layouts.main')

@section('content')

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
			<h3>Settings</h3>
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
			<label for="enabled" class="block">Product import - Stock threshold</label>
			<input type="text" id="stock_threshold" name="stock_threshold" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.stock_threshold') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Product import - Lower price threshold</label>
			<input type="text" id="lower_price_threshold" name="lower_price_threshold" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.lower_price_threshold') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Product import - Upper price threshold</label>
			<input type="text" id="upper_price_threshold" name="upper_price_threshold" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.upper_price_threshold') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Product import - Lower price threshold extra margin markup &pound;</label>
			<input type="text" id="lower_price_threshold_extra_margin_markup" name="lower_price_threshold_extra_margin_markup" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.lower_price_threshold_extra_margin_markup') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Product import - Margin markup %</label>
			<input type="text" id="margin_markup" name="margin_markup" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.margin_markup') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Order subtotal threshold to prevent cc option in checkout, and don&#39;t auto send order to Liv-Ex API</label>
			<input type="text" id="max_subtotal_in_basket" name="max_subtotal_in_basket" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.max_subtotal_in_basket') }}">
			</div>
		</div>
		
		<div class="card mt-4 p-4 w-full flex flex-wrap"><button type="submit" class="btn btn-secondary">Save</button> </div>
	</form>
	
	
	<form action="{{ route('admin.modules.livex.search_market') }}" method="get" class="flex flex-wrap mb-8">
		@csrf
		
		<div class="card mt-4 w-full">
			<h3>Liv-ex Search Market API</h3>
			<p><strong>NOTE:</strong> this is run via an automated routine every X minutes. Press the button below to run the routine manually.</p>
		</div>
		<div class="card mt-4 p-4 w-full flex flex-wrap">
			<button type="submit" class="btn btn-secondary">Process</button>
		</div>
	</form>
	
	
	<form action="{{ route('admin.modules.livex.search_market') }}" method="get" class="flex flex-wrap mb-8">
		@csrf
		
		<div class="card mt-4 w-full">
			<h3>Liv-ex API Errors</h3>
			
			<div class="w-full overflow-auto" style="height:15em;">
				@forelse($livex_errors as $livex_error)
					<div class="mt-3">
						<div><span class="font-medium">Order #{{ $livex_error->order_id }}</span> <span class="font-medium">{{ $livex_error->code }}</span> <span class="text-xs text-grey">@if($livex_error->created_at) &bullet; {{ $livex_error->created_at->diffForHumans() }}</span>@endif</div>
						<div class="py-1">{{ $livex_error->message }}</div>
					</div>
				@empty
					<div class="mt-3">No Liv-ex errors</div>
				@endforelse
			</div>
		</div>
	</form>
	
@endsection
