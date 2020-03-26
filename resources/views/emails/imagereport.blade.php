@component('mail::message')
Hello,<br>
<p>Below are the products reported without images.</p>
<p>A missing image may be due to; a missing library image in the LWIN placeholder folder, or missing tag data for colour/wine type.</p>
@foreach($products as $product)
{{ $product->model }} - {{ $product->name }} <br>
@endforeach
<br><br>
Thanks,<br>
{{ env('APP_NAME') }}
@endcomponent
