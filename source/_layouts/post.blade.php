@extends('_layouts.master')

@section('body')
<article>
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">
                @yield('content')
            </div>
        </div>
    </div>
</article>
@endsection