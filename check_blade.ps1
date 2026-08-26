$f = "e:\EasyKidsRobotics Competition\EasyKids Competition Tournament System\bracket-manage-system\resources\views\tournaments\bracket.blade.php"
$content = Get-Content $f -Raw
$pairs = @(
 ,("@if","@endif")
 ,("@foreach","@endforeach")
 ,("@forelse","@endforelse")
 ,("@push","@endpush")
 ,("@section","@endsection")
 ,("@php","@endphp")
 ,("@unless","@endunless")
 ,("@while","@endwhile")
)
foreach ($p in $pairs) {
  $open = ([regex]::Matches($content, [regex]::Escape($p[0]) + '(?!\w)')).Count
  $close = ([regex]::Matches($content, [regex]::Escape($p[1]))).Count
  Write-Output "$($p[0])=$open $($p[1])=$close"
}
