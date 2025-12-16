@extends('site.layout')

@section('title', 'Knowledge Base - Home')

@section('content')
    <div class="search-box">
        <input type="text" id="search" placeholder="Search knowledge entries..." onkeyup="filterEntries()">
    </div>

    <div class="content">
        <h2 style="margin-bottom: 20px;">Knowledge Entries</h2>

        @if(count($entries) === 0)
            <p style="color: #7f8c8d;">No entries found.</p>
        @else
            <div id="entries-list">
                @foreach($entries as $entry)
                    <div class="entry-item" data-title="{{ strtolower($entry->title) }}" data-content="{{ strtolower($entry->content) }}" data-category="{{ strtolower($entry->category ?? '') }}" data-tags="{{ strtolower(implode(' ', $entry->tags ?? [])) }}">
                        <h3 style="margin-bottom: 10px;">
                            <a href="entry-{{ $entry->id }}.html" style="color: #2c3e50; text-decoration: none;">
                                {{ $entry->title }}
                            </a>
                        </h3>

                        <div style="margin-bottom: 10px;">
                            @if($entry->category)
                                <span class="badge badge-primary">{{ $entry->category }}</span>
                            @endif
                            <span class="badge badge-secondary">{{ $entry->priority }}</span>
                            <span class="badge badge-success">{{ $entry->confidence }}%</span>
                        </div>

                        @if($entry->tags && count($entry->tags) > 0)
                            <div style="margin-bottom: 10px;">
                                @foreach($entry->tags as $tag)
                                    <span class="badge badge-warning">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif

                        <p style="color: #7f8c8d; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ecf0f1;">
                            {{ \Illuminate\Support\Str::limit($entry->content, 200) }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@section('scripts')
<script>
function filterEntries() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const entries = document.querySelectorAll('.entry-item');

    entries.forEach(entry => {
        const title = entry.getAttribute('data-title');
        const content = entry.getAttribute('data-content');
        const category = entry.getAttribute('data-category');
        const tags = entry.getAttribute('data-tags');

        const matches = title.includes(searchTerm) ||
                       content.includes(searchTerm) ||
                       category.includes(searchTerm) ||
                       tags.includes(searchTerm);

        entry.style.display = matches ? 'block' : 'none';
    });
}
</script>
@endsection
