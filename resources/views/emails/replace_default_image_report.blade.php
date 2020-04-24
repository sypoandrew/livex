@component('mail::message')
Hello,<br>
<p>A new report has been created to highlight items without a library images.</p>
<p>Please log into the admin to download the report from the Modules area.</p>
<br><br>
Thanks,<br>
{{ env('APP_NAME') }}
@endcomponent
