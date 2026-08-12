<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="material-icons-outlined font-18 me-1">tune</i> Environment Variables</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEnvModal">
                <i class="material-icons-outlined font-18">add</i> Add New Variable
            </button>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="material-icons-outlined font-18 me-1">check_circle</i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="material-icons-outlined font-18 me-1">error</i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Search and Filter -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <input type="text" id="searchEnv" class="form-control" placeholder="Search by key or value...">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-secondary w-100" onclick="expandAllSections()">
                            <i class="material-icons-outlined font-18">unfold_more</i> Expand All
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Environment Variables by Section -->
        <div class="accordion" id="envSectionsAccordion">
            @php
                $sectionIcons = [
                    'Application' => 'apps',
                    'Database' => 'storage',
                    'Cache' => 'cached',
                    'Queue' => 'queue',
                    'Mail' => 'email',
                    'AWS' => 'cloud',
                    'Broadcasting' => 'wifi',
                    'SMS' => 'sms',
                    'Payment' => 'payment',
                    'API' => 'api',
                    'Custom' => 'settings',
                    'General' => 'dashboard',
                ];
            @endphp

            @forelse($envSections as $sectionName => $variables)
                <div class="card mb-2 env-section" data-section="{{ strtolower($sectionName) }}">
                    <div class="card-header" id="heading{{ Str::slug($sectionName) }}">
                        <h2 class="mb-0">
                            <button class="btn btn-link btn-block text-start d-flex justify-content-between align-items-center" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#collapse{{ Str::slug($sectionName) }}" 
                                    aria-expanded="true" 
                                    aria-controls="collapse{{ Str::slug($sectionName) }}">
                                <span>
                                    <i class="material-icons-outlined font-18 me-2">{{ $sectionIcons[$sectionName] ?? 'folder' }}</i>
                                    <strong>{{ $sectionName }}</strong>
                                    <span class="badge bg-primary ms-2">{{ count($variables) }} vars</span>
                                </span>
                                <i class="material-icons-outlined font-18">expand_more</i>
                            </button>
                        </h2>
                    </div>

                    <div id="collapse{{ Str::slug($sectionName) }}" 
                         class="collapse {{ $loop->first ? 'show' : '' }}" 
                         aria-labelledby="heading{{ Str::slug($sectionName) }}" 
                         data-bs-parent="#envSectionsAccordion">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 35%;">Key</th>
                                            <th style="width: 50%;">Value</th>
                                            <th style="width: 15%;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($variables as $key => $value)
                                            <tr class="env-row" data-key="{{ strtolower($key) }}" data-value="{{ strtolower($value) }}">
                                                <td>
                                                    <code class="text-primary fw-bold">{{ $key }}</code>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        @php
                                                            $isSensitive = Str::contains(strtolower($key), ['password', 'secret', 'key', 'token']);
                                                        @endphp
                                                        @if($isSensitive && !empty($value))
                                                            <code class="text-muted text-break env-value-hidden">••••••••••••</code>
                                                            <code class="text-muted text-break env-value-shown d-none">{{ Str::limit($value, 100) }}</code>
                                                            <button type="button" class="btn btn-sm btn-link p-0 ms-2 toggle-visibility" title="Toggle visibility">
                                                                <i class="material-icons-outlined font-18">visibility</i>
                                                            </button>
                                                        @else
                                                            <code class="text-muted text-break">{{ Str::limit($value, 100) }}</code>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group" role="group">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-info edit-env-btn" 
                                                                onclick="editEnvVariable('{{ $key }}', `{{ addslashes($value) }}`)"
                                                                title="Edit">
                                                            <i class="material-icons-outlined font-18">edit</i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteEnvModal"
                                                                data-key="{{ $key }}"
                                                                title="Delete">
                                                            <i class="material-icons-outlined font-18">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="material-icons-outlined" style="font-size: 64px; color: #6c757d;">inbox</i>
                        <h5 class="mt-3 text-muted">No environment variables found</h5>
                        <p class="text-muted">Click "Add New Variable" to get started.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="alert alert-info mt-3">
            <i class="material-icons-outlined font-18 me-1">info</i>
            <strong>Info:</strong> Variables are automatically grouped by their prefix (DB_, MAIL_, SMS_, etc.). Config cache is cleared automatically after each change.
        </div>
    </div>
</div>

<!-- Add Environment Variable Modal -->
<div class="modal fade" id="addEnvModal" tabindex="-1" aria-labelledby="addEnvModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.settings.env.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addEnvModalLabel">
                        <i class="material-icons-outlined font-18 me-1">add_circle</i>
                        Add Environment Variable
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="env_section" class="form-label">Section</label>
                        <select class="form-select" id="env_section" name="section">
                            <option value="Application">Application</option>
                            <option value="Database">Database</option>
                            <option value="Cache">Cache</option>
                            <option value="Queue">Queue</option>
                            <option value="Mail">Mail</option>
                            <option value="AWS">AWS</option>
                            <option value="Broadcasting">Broadcasting</option>
                            <option value="SMS">SMS</option>
                            <option value="Payment">Payment</option>
                            <option value="API">API</option>
                            <option value="Custom" selected>Custom</option>
                        </select>
                        <small class="form-text text-muted">
                            <i class="material-icons-outlined font-14">info</i>
                            Choose a section or create a custom one
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="env_key" class="form-label">Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="env_key" name="key" required 
                               placeholder="e.g., APP_NAME, DB_HOST, CUSTOM_SETTING"
                               pattern="[A-Z_][A-Z0-9_]*"
                               title="Only uppercase letters, numbers, and underscores. Must start with a letter or underscore.">
                        <small class="form-text text-muted">
                            <i class="material-icons-outlined font-14">info</i>
                            Use uppercase letters and underscores only (e.g., MY_CUSTOM_KEY)
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="env_value" class="form-label">Value <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="env_value" name="value" rows="3" required 
                                  placeholder="Enter value"></textarea>
                        <small class="form-text text-muted">
                            <i class="material-icons-outlined font-14">info</i>
                            Values with spaces will be automatically quoted
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined font-18">close</i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons-outlined font-18">save</i> Add Variable
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Environment Variable Modal -->
<div class="modal fade" id="editEnvModal" tabindex="-1" aria-labelledby="editEnvModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.settings.env.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editEnvModalLabel">
                        <i class="material-icons-outlined font-18 me-1">edit</i>
                        Edit Environment Variable
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_env_key" class="form-label">Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light" id="edit_env_key" name="key" readonly>
                        <input type="hidden" id="edit_env_old_key" name="old_key">
                        <small class="form-text text-muted">
                            <i class="material-icons-outlined font-14">lock</i>
                            Key cannot be changed. Delete and create new if needed.
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="edit_env_value" class="form-label">Value <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit_env_value" name="value" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined font-18">close</i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons-outlined font-18">save</i> Update Variable
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Environment Variable Modal -->
<div class="modal fade" id="deleteEnvModal" tabindex="-1" aria-labelledby="deleteEnvModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.settings.env.delete') }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteEnvModalLabel">
                        <i class="material-icons-outlined font-18 me-1">warning</i>
                        Delete Environment Variable
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delete_env_key" name="key">
                    <p>Are you sure you want to delete the environment variable: <strong class="text-danger" id="delete_env_key_display"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="material-icons-outlined font-18">warning</i> 
                        <strong>Warning:</strong> This action cannot be undone. Make sure you have a backup of your .env file.
                    </div>
                    <div class="alert alert-info">
                        <i class="material-icons-outlined font-18">info</i> 
                        <strong>Note:</strong> Critical variables like APP_KEY, APP_ENV, APP_DEBUG cannot be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined font-18">close</i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="material-icons-outlined font-18">delete_forever</i> Delete Variable
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    .btn-link {
        text-decoration: none;
        color: #333;
        width: 100%;
    }
    .btn-link:hover {
        color: #0d6efd;
    }
    .accordion .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    .env-value-hidden, .env-value-shown {
        font-size: 0.875rem;
    }
    .toggle-visibility {
        cursor: pointer;
        color: #6c757d;
    }
    .toggle-visibility:hover {
        color: #0d6efd;
    }
    .env-row {
        transition: background-color 0.2s;
    }
    .env-row:hover {
        background-color: #f8f9fa;
    }
</style>
@endpush

@push('scripts')
<script>
// Function to open edit modal with values - DEFINED FIRST!
window.editEnvVariable = function(key, value) {
    console.log('=== Edit Function Called ===' );
    console.log('Key:', key);
    console.log('Value:', value);
    console.log('Value type:', typeof value);
    console.log('Value length:', value ? value.length : 0);
    
    // Set modal values
    const modalKeyInput = document.getElementById('edit_env_key');
    const modalOldKeyInput = document.getElementById('edit_env_old_key');
    const modalValueInput = document.getElementById('edit_env_value');
    
    console.log('Modal inputs found:', {
        key: modalKeyInput ? 'yes' : 'no',
        oldKey: modalOldKeyInput ? 'yes' : 'no',
        value: modalValueInput ? 'yes' : 'no'
    });
    
    if (modalKeyInput && modalOldKeyInput && modalValueInput) {
        modalKeyInput.value = key || '';
        modalOldKeyInput.value = key || '';
        modalValueInput.value = value || '';
        
        console.log('Values set:');
        console.log('  Key field:', modalKeyInput.value);
        console.log('  Old key field:', modalOldKeyInput.value);
        console.log('  Value field:', modalValueInput.value);
        console.log('  Value textarea innerHTML:', modalValueInput.innerHTML);
        
        // Open the modal
        const editModal = new bootstrap.Modal(document.getElementById('editEnvModal'));
        editModal.show();
        console.log('Modal opened');
    } else {
        console.error('Modal inputs not found!');
        alert('Error: Modal fields not found. Please refresh the page.');
    }
};

console.log('editEnvVariable function defined:', typeof window.editEnvVariable);

document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded - edit modal ready');
    console.log('editEnvVariable available:', typeof window.editEnvVariable);
    
    // Delete Modal
    const deleteModal = document.getElementById('deleteEnvModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const key = button.getAttribute('data-key');
            
            const modalKeyInput = deleteModal.querySelector('#delete_env_key');
            const modalKeyDisplay = deleteModal.querySelector('#delete_env_key_display');
            
            modalKeyInput.value = key;
            modalKeyDisplay.textContent = key;
        });
    }

    // Auto-uppercase the key input in Add modal
    const addKeyInput = document.getElementById('env_key');
    if (addKeyInput) {
        addKeyInput.addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    }

    // Toggle visibility for sensitive values
    document.querySelectorAll('.toggle-visibility').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('td');
            const hiddenValue = row.querySelector('.env-value-hidden');
            const shownValue = row.querySelector('.env-value-shown');
            const icon = this.querySelector('i');
            
            if (hiddenValue.classList.contains('d-none')) {
                hiddenValue.classList.remove('d-none');
                shownValue.classList.add('d-none');
                icon.textContent = 'visibility';
            } else {
                hiddenValue.classList.add('d-none');
                shownValue.classList.remove('d-none');
                icon.textContent = 'visibility_off';
            }
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchEnv');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.env-row');
            const sections = document.querySelectorAll('.env-section');
            
            rows.forEach(row => {
                const key = row.getAttribute('data-key');
                const value = row.getAttribute('data-value');
                
                if (key.includes(searchTerm) || value.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Hide empty sections
            sections.forEach(section => {
                const visibleRows = section.querySelectorAll('.env-row:not([style*="display: none"])');
                if (visibleRows.length === 0 && searchTerm !== '') {
                    section.style.display = 'none';
                } else {
                    section.style.display = '';
                }
            });
        });
    }
});

// Expand all sections
function expandAllSections() {
    const collapses = document.querySelectorAll('.collapse');
    collapses.forEach(collapse => {
        const bsCollapse = new bootstrap.Collapse(collapse, {
            toggle: false
        });
        bsCollapse.show();
    });
}
</script>
@endpush

<!-- CRITICAL: Inline script for edit function - loaded immediately -->
<script type="text/javascript">
(function() {
    console.log('=== INLINE EDIT SCRIPT LOADING ===');
    
    // Define function on window object for global access
    window.editEnvVariable = function(key, value) {
        console.log('=== Edit Function Called ===');
        console.log('Key:', key);
        console.log('Value:', value);
        console.log('Value type:', typeof value);
        console.log('Value length:', value ? value.length : 0);
        
        // Set modal values
        var modalKeyInput = document.getElementById('edit_env_key');
        var modalOldKeyInput = document.getElementById('edit_env_old_key');
        var modalValueInput = document.getElementById('edit_env_value');
        
        console.log('Modal inputs found:', {
            key: modalKeyInput ? 'yes' : 'no',
            oldKey: modalOldKeyInput ? 'yes' : 'no',
            value: modalValueInput ? 'yes' : 'no'
        });
        
        if (modalKeyInput && modalOldKeyInput && modalValueInput) {
            modalKeyInput.value = key || '';
            modalOldKeyInput.value = key || '';
            modalValueInput.value = value || '';
            
            console.log('Values set:');
            console.log('  Key field:', modalKeyInput.value);
            console.log('  Old key field:', modalOldKeyInput.value);
            console.log('  Value field:', modalValueInput.value);
            
            // Open the modal
            var editModalEl = document.getElementById('editEnvModal');
            var editModal = new bootstrap.Modal(editModalEl);
            editModal.show();
            console.log('Modal opened');
        } else {
            console.error('Modal inputs not found!');
            alert('Error: Modal fields not found. Please refresh the page.');
        }
    };
    
    console.log('INLINE editEnvVariable function defined:', typeof window.editEnvVariable);
})();
</script>
