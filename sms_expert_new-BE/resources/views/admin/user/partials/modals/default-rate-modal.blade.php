 <div class="modal fade" id="defaultRateModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog">
         <div class="modal-content">
             <div class="modal-header">
                 <h5 class="modal-title">Update Default SMS Rate</h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
             </div>
             <form method="POST" action="{{ route('admin.user.rate.default', $record->id) }}">
                 @csrf
                 @method('PUT')
                 <div class="modal-body">
                     <p>This rate will be applied to all countries without
                         custom rates.</p>
                     <div class="form-group">
                         <label for="default_rate" class="form-label fw-semibold">Default Rate per
                             SMS (£)</label>
                         <input type="number" class="form-control" id="default_rate" name="default_rate" step="0.0001"
                             min="0" value="{{ $defaultRate ?? 0.03 }}" placeholder="0.0000" required>
                     </div>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                     <button type="submit" class="btn btn-primary">Update
                         Default Rate</button>
                 </div>
             </form>
         </div>
     </div>
 </div>
