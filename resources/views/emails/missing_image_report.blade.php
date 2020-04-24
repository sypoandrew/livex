@component('mail::message')
Hello,<br>
<p>A new report has been created to highlight items without images.</p>
<p>A missing image may be due to; a missing library image in the LWIN placeholder folder, or missing tag data for colour/wine type.</p>
<p>Please log into the admin to download the report from the Modules area.</p>
<br><br>
Thanks,<br>
{{ env('APP_NAME') }}
@endcomponent
