<div class="modal fade" id="mapKeywordModal{{ $shortcode->id }}" tabindex="-1"
    aria-labelledby="mapKeywordLabel{{ $shortcode->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top modal-dialog-scrollable">
        <div class="modal-content">
            <form
                action="{{ route('mapping.keyword.update', ['virtual_id' => $shortcode->id, 'userid' => $record->id]) }}"
                method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="mapKeywordLabel{{ $shortcode->id }}">
                        Map Keyword</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="keyword{{ $shortcode->id }}" class="form-label">Select Keyword</label>
                    <select class="form-select" name="keyword" id="keyword{{ $shortcode->id }}" required>
                        <option value="" disabled {{ !empty($shortcode->id) ? 'selected' : '' }}>
                            -- Select Keyword --
                        </option>

                        @foreach ($keywords as $keyword)
                            <option value="{{ $keyword->id }}"
                                {{ old('keyword', $shortcode->id) == $keyword->smsshortcodes_id ? 'selected' : '' }}>
                                {{ $keyword->keyword }}
                            </option>
                        @endforeach
                    </select>

                    {{-- <select class="form-select" name="keyword"
                                                                                id="keyword{{ $shortcode->id }}"
                                                                                required>
                                                                                <option value="" disabled selected>--
                                                                                    Select Keyword --</option>
                                                                                @foreach ($keywords as $id => $keyword)
                                                                                    <option value="{{ $keyword->id }}">
                                                                                        {{ $keyword->keyword }}</option>
                                                                                @endforeach
                                                                            </select> --}}
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Map</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
