<div class="tab-pane fade {{ session('activeTab') == 'customer-client-note' ? 'show active' : '' }}"
    id="customer-client-note" role="tabpanel">
    
    <div class="col-12">
        <div class="row g-2">
            <div class="col-12 col-md-auto">
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addClientNoteModal">
                    Add New Notes
                </button>
            </div>
        </div>
    </div>
    
    <br>
    
    <div class="table-responsive">
        <table id="customer-client-note-view" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr class="text-center">
                    <th>Notes</th>
                    <th>Created By</th>
                    <th>Date</th>
                    <th>Options</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($client_notes as $client_note)
                    <tr class="text-center">
                        <td class="text-start">
                            @php
                                $noteContent = trim($client_note->notes ?? '');
                                $noteContent = $noteContent !== '' ? nl2br(htmlspecialchars($noteContent)) : 'No content';
                                $noteContent = preg_replace('/(https?:\/\/[^\s<]+)/i', '<a href="$1" target="_blank" class="text-primary">Download Invoice</a>', $noteContent);
                            @endphp
                            <div style="max-height: 150px; overflow-y: auto; word-wrap: break-word; max-width: 500px;">{!! $noteContent !!}</div>
                        </td>
                        <td>{{ $client_note->staffinitials ?? '' }}</td>
                        <td>{{ \Carbon\Carbon::parse($client_note->insertdate)->format('D jS M y H.i') }}</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm"
                                data-bs-toggle="modal" data-bs-target="#deleteNoteModal{{ $client_note->id }}">
                                Delete
                            </button>
                        </td>
                    </tr>

                    {{-- Delete Note Modal --}}
                    @include('admin.user.partials.modals.delete-client-note-modal', ['client_note' => $client_note])
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Add Client Note Modal --}}
@include('admin.user.partials.modals.add-client-note-modal')