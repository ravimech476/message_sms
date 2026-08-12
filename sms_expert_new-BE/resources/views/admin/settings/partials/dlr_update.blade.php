{{-- DLR Status Update — upload a Vonage/Nexmo delivery-report CSV export.
     The rows are queued to RabbitMQ (nexmo.delivery.reports) and the running DLR consumer
     (nexmo:process-delivery-queue) matches each message_id to smsg_log and updates
     deliverystatus2 + fires the customer DLR push callback — identical to
     nexmo:fetch-delivery-reports / the `dlr:import-csv` command. --}}
<div class="row">
    <div class="col-12 col-xl-9">
        <div class="card border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0"><i class="material-icons-outlined align-middle me-1">cloud_upload</i> DLR Status Update</h5>
            </div>
            <div class="card-body">

                @if(session('dlr_result'))
                    @php($r = session('dlr_result'))
                    <div class="alert alert-success">
                        <h6 class="mb-1"><i class="material-icons-outlined align-middle me-1">check_circle</i> Upload processed</h6>
                        <div class="small">
                            File: <strong>{{ $r['file'] ?? '' }}</strong> &middot;
                            Rows read: <strong>{{ $r['read'] ?? 0 }}</strong> &middot;
                            Queued to RabbitMQ: <strong>{{ $r['queued'] ?? 0 }}</strong>
                            @if(($r['skipped'] ?? 0) > 0) &middot; Skipped: <strong>{{ $r['skipped'] }}</strong> @endif
                        </div>
                        <div class="small mt-1 text-muted">
                            The DLR consumer (<code>nexmo.delivery.reports</code>) will match each <code>message_id</code>
                            and update <code>deliverystatus2</code> shortly. Make sure
                            <code>nexmo:process-delivery-queue</code> is running.
                        </div>
                    </div>
                @endif

                @if(session('dlr_error'))
                    <div class="alert alert-danger">
                        <i class="material-icons-outlined align-middle me-1">error</i> {{ session('dlr_error') }}
                    </div>
                @endif

                @error('dlr_file')
                    <div class="alert alert-danger"><i class="material-icons-outlined align-middle me-1">error</i> {{ $message }}</div>
                @enderror

                <p class="text-muted">
                    Upload the Vonage/Nexmo delivery-report export (<strong>.csv</strong>). Each row is pushed to
                    RabbitMQ and processed asynchronously to update the delivery status of matching messages.
                </p>

                <form action="{{ route('admin.settings.dlr.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="dlr_file" class="form-label">DLR CSV file</label>
                        <input type="file" class="form-control" id="dlr_file" name="dlr_file" accept=".csv,text/csv" required>
                        <div class="form-text">
                            Required columns: <code>message_id</code>, <code>status</code>
                            (optional: <code>error_code</code>, <code>error_code_description</code>, <code>total_price</code>).
                            Matching is by <code>message_id</code> &rarr; <code>smsg_log.onesixty_suppliermsgref</code>.
                            Maximum file size: <strong>64&nbsp;MB</strong>.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="dlr_mode" class="form-label">Processing mode</label>
                        <select class="form-select" id="dlr_mode" name="dlr_mode">
                            <option value="queue" selected>Queue to RabbitMQ (recommended — consumer applies updates)</option>
                            <option value="sync">Process inline now (no consumer needed)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="material-icons-outlined align-middle me-1">upload</i> Upload &amp; Process
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>
