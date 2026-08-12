<div class="modal fade" id="editKeywordModal{{ $keyword->id }}" tabindex="-1"
    aria-labelledby="editKeywordModalLabel{{ $keyword->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('keyword.update', $keyword->id) }}" method="POST">
                @csrf
                @method('POST')
                <!-- or PUT if using method spoofing -->
                <div class="modal-header">
                    <h5 class="modal-title" id="editKeywordModalLabel{{ $keyword->id }}">
                        Edit Keyword</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">Keyword</label>
                            <input type="text" name="keyword" class="form-control"
                                value="{{ $keyword->keyword ?? '' }}" required>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">Forwarding
                                Email</label>
                            <input type="email" name="theemail" class="form-control"
                                value="{{ $keyword->forwarding_email ?? '' }}" required>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">Start
                                Date</label>
                            <input type="date" name="purchased" class="form-control"
                                value="{{ $keyword->purchased }}" required>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">End
                                Date</label>
                            <input type="date" name="expiry" class="form-control" value="{{ $keyword->expiry }}"
                                required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
