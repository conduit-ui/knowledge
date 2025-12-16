@extends('site.layout')

@section('title', 'Tags - Knowledge Base')

@section('content')
    <div class="content">
        <h2 style="margin-bottom: 20px;">Tags</h2>

        @if(count($tags) === 0)
            <p style="color: #7f8c8d;">No tags found.</p>
        @else
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px;">
                @foreach($tags as $tag)
                    <a href="#tag-{{ $tag->slug }}" style="text-decoration: none;">
                        <span class="badge badge-warning" style="font-size: 1em; cursor: pointer;">
                            {{ $tag->name }} ({{ $tag->count }})
                        </span>
                    </a>
                @endforeach
            </div>

            @foreach($tags as $tag)
                <div id="tag-{{ $tag->slug }}" style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #ecf0f1;">
                    <h3 style="margin-bottom: 10px;">{{ $tag->name }}</h3>
                    <p style="color: #7f8c8d; margin-bottom: 10px;">{{ $tag->count }} entries</p>

                    <ul style="margin-left: 20px;">
                        @foreach($tag->entries as $entry)
                            <li style="margin-bottom: 8px;">
                                <a href="entry-{{ $entry->id }}.html" style="color: #2c3e50; text-decoration: none;">
                                    {{ $entry->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @endif
    </div>
@endsection
