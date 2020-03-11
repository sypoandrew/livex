@extends('admin::layouts.main')

@section('content')
    <div class="flex pb-2 mb-4">
        <h2 class="flex-1 m-0 p-0">Liv-Ex</h2>
    </div>
    @include('admin::partials.alerts')
    <div class="card">
        Livex API integration settings for Aero Commerce
		<form action="{{ route('admin.modules.livex') }}" method="post" class="flex flex-wrap">
		<div>
		<label for="enabled" class="block">Enabled</label>
		<input type="text" id="name" name="name" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.enabled') }}">
		</div>
		<div>
		<label for="enabled" class="block">Rates per X litre</label>
		<input type="text" id="name" name="name" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.litre_calc') }}">
		</div>
		<div>
		<label for="enabled" class="block">Still Wine Rate</label>
		<input type="text" id="name" name="name" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.still_wine_rate') }}">
		</div>
		<div>
		<label for="enabled" class="block">Sparkling Wine Rate</label>
		<input type="text" id="name" name="name" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.sparkling_wine_rate') }}">
		</div>
		<div>
		<label for="enabled" class="block">Fortified Wine Rate</label>
		<input type="text" id="name" name="name" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.fortified_wine_rate') }}">
		</div>
		</form>
		
    </div>
@endsection
