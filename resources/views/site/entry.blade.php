@extends('site.layout')

@section('title', $entry->title . ' - Knowledge Base')

@section('content')
    <div class="content">
        <h1 style="margin-bottom: 20px;">{{ $entry->title }}</h1>

        <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #ecf0f1;">
            @if($entry->category)
                <span class="badge badge-primary">{{ $entry->category }}</span>
            @endif
            @if($entry->module)
                <span class="badge badge-secondary">{{ $entry->module }}</span>
            @endif
            <span class="badge badge-secondary">{{ $entry->priority }}</span>
            <span class="badge badge-success">{{ $entry->confidence }}%</span>
            <span class="badge badge-secondary">{{ $entry->status }}</span>
        </div>

        @if($entry->tags && count($entry->tags) > 0)
            <div style="margin-bottom: 20px;">
                <strong>Tags:</strong>
                @foreach($entry->tags as $tag)
                    <span class="badge badge-warning">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        <div style="margin: 30px 0; line-height: 1.8; white-space: pre-wrap;">{{ $entry->content }}</div>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ecf0f1; color: #7f8c8d; font-size: 0.9em;">
            <h3 style="margin-bottom: 15px; color: #2c3e50;">Metadata</h3>

            @if($entry->source)
                <p><strong>Source:</strong> {{ $entry->source }}</p>
            @endif

            @if($entry->ticket)
                <p><strong>Ticket:</strong> {{ $entry->ticket }}</p>
            @endif

            @if($entry->author)
                <p><strong>Author:</strong> {{ $entry->author }}</p>
            @endif

            @if($entry->files && count($entry->files) > 0)
                <p><strong>Files:</strong></p>
                <ul style="margin-left: 20px; margin-top: 5px;">
                    @foreach($entry->files as $file)
                        <li>{{ $file }}</li>
                    @endforeach
                </ul>
            @endif

            @if($entry->repo)
                <p><strong>Repository:</strong> {{ $entry->repo }}</p>
            @endif

            @if($entry->branch)
                <p><strong>Branch:</strong> {{ $entry->branch }}</p>
            @endif

            @if($entry->commit)
                <p><strong>Commit:</strong> {{ $entry->commit }}</p>
            @endif

            <p><strong>Usage Count:</strong> {{ $entry->usage_count }}</p>

            @if($entry->last_used)
                <p><strong>Last Used:</strong> {{ $entry->last_used->format('Y-m-d H:i:s') }}</p>
            @endif

            <p><strong>Created:</strong> {{ $entry->created_at->format('Y-m-d H:i:s') }}</p>
            <p><strong>Updated:</strong> {{ $entry->updated_at->format('Y-m-d H:i:s') }}</p>
        </div>

        <div style="margin-top: 30px;">
            <a href="index.html" style="color: #3498db; text-decoration: none;">&larr; Back to all entries</a>
        </div>
    </div>
@endsection
