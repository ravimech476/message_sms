<div class="modal fade" id="addKeywordModal" tabindex="-1" aria-labelledby="addKeywordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('keyword.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addKeywordModalLabel">Add New Keyword
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">Keyword</label>
                            <input type="text" class="form-control" name="demokeyword" id="demokeyword" required>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">Forwarding
                                Email</label>
                            <input type="email" class="form-control" name="theemail" id="theemail" required>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" class="form-control" name="keywordstartdate" required>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" class="form-control" name="keywordenddate" required>
                        </div>
                        <input type="hidden" name="userid" value="{{ $record->id ?? '' }}" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
