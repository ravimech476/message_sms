<div class="card border">
    <div class="card-body">
       

        <h5 class="card-title">Keyword Details</h5>

        <form id="keywordForm" class="row g-3">
            @csrf
            <div class="col-md-6">
                <select class="form-select" name="seliTAGG" id="seliTAGG" required>
                    <option value="">-- Select an Keyword --</option>
                    @foreach ($keywords as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->keyword ?? 'ID: ' . $item->id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">View Details</button>
            </div>
        </form>
        <div id="keywordDetails" class="mt-3"></div>
    </div>
</div>