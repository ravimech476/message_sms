<div class="modal fade" id="addClientNoteModal" tabindex="-1"
                                            aria-labelledby="addClientNoteModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form action="{{ route('client.note.store') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="users_bigid"
                                                            value="{{ $record->bigid }}">
                                                        <input type="hidden" name="user_id"
                                                            value="{{ $record->id }}">

                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="addClientNoteModalLabel">Add New
                                                                Client Note</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>

                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="notes"
                                                                    class="form-label fw-semibold">Notes <span
                                                                        class="text-danger">*</span></label>
                                                                <textarea class="form-control" id="notes" name="notes" rows="5" required
                                                                    placeholder="Enter your notes here..."></textarea>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="staffinitials"
                                                                            class="form-label fw-semibold">Staff
                                                                            Initials</label>
                                                                        @php
                                                                            $staffName = auth()->user()->contactname
                                                                                ?? (Session::get('admin_user')['contactname'] ?? (auth()->user()->name ?? 'admin'));
                                                                            $staffName = trim(urldecode((string) $staffName));
                                                                        @endphp
                                                                        <input type="text" class="form-control bg-light"
                                                                            id="staffinitials" name="staffinitials"
                                                                            value="{{ $staffName }}"
                                                                            maxlength="35" readonly>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="nextcontactdate"
                                                                            class="form-label fw-semibold">Next Contact
                                                                            Date</label>
                                                                        <input type="date" class="form-control"
                                                                            id="nextcontactdate" name="nextcontactdate"
                                                                            value="{{ date('Y-m-d', strtotime('+7 days')) }}">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save
                                                                Note</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>