<pre class="rounded-md border bg-gray-50 p-3 text-sm overflow-auto">
{{ json_encode($getRecord()->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
</pre>
