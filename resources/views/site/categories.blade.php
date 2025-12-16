@extends('site.layout')

@section('title', 'Categories - Knowledge Base')

@section('content')
    <div class="content">
        <h2 style="margin-bottom: 20px;">Categories</h2>

        @if(count($categories) === 0)
            <p style="color: #7f8c8d;">No categories found.</p>
        @else
            @foreach($categories as $category)
                <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #ecf0f1;">
                    <h3 style="margin-bottom: 10px;">{{ $category->name ?? 'Uncategorized' }}</h3>
                    <p style="color: #7f8c8d; margin-bottom: 10px;">{{ $category->count }} entries</p>

                    <ul style="margin-left: 20px;">
                        @foreach($category->entries as $entry)
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
