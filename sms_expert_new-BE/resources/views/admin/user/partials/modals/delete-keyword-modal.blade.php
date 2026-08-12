<div class="modal fade" id="deleteKeywordModal{{ $keyword->id }}" tabindex="-1"
    aria-labelledby="deleteLabel{{ $keyword->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('keyword.delete', $keyword->id) }}">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteLabel{{ $keyword->id }}">Confirm
                        Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete keyword:
                    <strong>{{ $keyword->keyword }}</strong> ?
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
