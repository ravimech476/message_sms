<div class="modal fade" id="deleteNoteModal{{ $client_note->id }}" tabindex="-1"
    aria-labelledby="deleteNoteLabel{{ $client_note->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('client.note.destroy', $client_note->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteNoteLabel{{ $client_note->id }}">
                        Confirm
                        Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this note?
                    <blockquote class="blockquote mt-2 mb-0">
                        <p class="small text-muted"
                            style="white-space: pre-wrap; word-wrap: break-word; max-height: 200px; overflow-y: auto;">
                            {{ substr($client_note->notes ?? 'No content', 0, 500) }}{{ strlen($client_note->notes ?? '') > 500 ? '...' : '' }}
                        </p>
                    </blockquote>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Yes,
                        Delete</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
