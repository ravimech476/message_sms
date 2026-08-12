{{-- Virtual Numbers Tab Content --}}
<div class="tab-pane fade {{ session('activeTab') == 'customer-virtual-number' ? 'show active' : '' }}"
    id="customer-virtual-number" role="tabpanel">
    <div class="bs-stepper-content">
        <div id="test-l-3" role="tabpanel" class="bs-stepper-pane">
            
            {{-- Alert Container --}}
            <div id="virtual-numbers-alerts"></div>
            
            <div class="card border">
                <div class="card-body">
                    <h5 class="card-title mb-4">Virtual Numbers + Shortcode Keywords</h5>
                    
                    @php
                        $today = \Carbon\Carbon::now()->format('Y-m-d');
                        $bigid = $record->bigid;
                        
                        // Fetch virtual numbers
                        $virtualNumbers = \App\Models\ItaggInstance::select(
                                'itagg_instance.id as id',
                                'itagg_instance.keyword as keyword',
                                'smsshortcodes.number as number',
                                'smsshortcodes.id as shortcodeid',
                                'itagg_instance.expiry as theexpiry',
                                'itagg_instance.active as isactive',
                                'smsshortcodes.whichoperator as theprovider',
                                'itagg_instance.forwarding_email',
                                'smsshortcodes.pooled'
                            )
                            ->join('smsshortcodes', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
                            ->where('itagg_instance.users_bigid', $bigid)
                            ->where(function($query) use ($today) {
                                $query->where('itagg_instance.expiry', '>=', $today)
                                      ->orWhere('itagg_instance.expiry', '=', '1999-05-19')
                                      ->orWhere('itagg_instance.expiry', '<=', $today);
                            })
                            ->where('itagg_instance.status', '1')
                            ->orderBy('smsshortcodes.number', 'desc')
                            ->orderBy('itagg_instance.active', 'desc')
                            ->orderBy('itagg_instance.expiry', 'asc')
                            ->orderBy('itagg_instance.keyword', 'asc')
                            ->get();
                            
                        // Group by shortcode
                        $groupedNumbers = $virtualNumbers->groupBy('number');
                    @endphp
                    
                    @if($virtualNumbers->isEmpty())
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> No virtual numbers found for this user.
                        </div>
                    @else
                        @foreach($groupedNumbers as $shortcode => $keywords)
                            @php
                                $firstKeyword = $keywords->first();
                            @endphp
                            
                            <div class="mb-4">
                                <h6 class="fw-bold text-primary">
                                    '{{ $shortcode }}' 
                                    <small class="text-muted">
                                        (ID: {{ $firstKeyword->shortcodeid }}, 
                                        Provider: {{ $firstKeyword->theprovider }}, 
                                        Pooled: {{ $firstKeyword->pooled }})
                                    </small>
                                </h6>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="15%">Keyword</th>
                                                <th width="15%">Status</th>
                                                <th width="35%">Expiry & Actions</th>
                                                <th width="20%">Forwarding Email</th>
                                                <th width="15%">Manage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($keywords as $index => $item)
                                                @php
                                                    $isActive = $item->isactive == 1;
                                                    $newActiveState = $isActive ? 0 : 1;
                                                    $newActiveStateStr = $isActive ? 'suspend' : 'unsuspend';
                                                    $isActiveText = $isActive ? '' : ' / <span class="text-danger">suspended</span>';
                                                    
                                                    $todayTime = date('Ymd');
                                                    $thisExpiryDate = date('Ymd', strtotime($item->theexpiry));
                                                    
                                                    // Check if force expired
                                                    $isForceExpired = ($thisExpiryDate == '19990519');
                                                    
                                                    // Check if expired
                                                    $isExpired = ($item->theexpiry <= $today && !$isForceExpired);
                                                    
                                                    $expiryClass = '';
                                                    if ($isForceExpired) {
                                                        $expiryClass = 'table-danger';
                                                    } elseif ($isExpired) {
                                                        $expiryClass = 'table-warning';
                                                    } elseif (!$isActive) {
                                                        $expiryClass = 'table-secondary';
                                                    }
                                                @endphp
                                                
                                                <tr class="{{ $expiryClass }}">
                                                    <td>
                                                        <strong>{{ $item->keyword }}</strong>
                                                    </td>
                                                    
                                                    <td>
                                                        @if($isForceExpired)
                                                            <span class="badge bg-danger">F-Expired</span>
                                                        @elseif($isExpired)
                                                            <span class="badge bg-warning text-dark">Expired</span>
                                                        @else
                                                            <span class="badge bg-success">Active</span>
                                                        @endif
                                                        
                                                        @if(!$isActive)
                                                            <span class="badge bg-secondary">Suspended</span>
                                                        @endif
                                                    </td>
                                                    
                                                    <td>
                                                        @if($isForceExpired)
                                                            <div class="mb-2">
                                                                <span class="text-danger fw-bold">Force-Expired</span>
                                                                <div class="btn-group btn-group-sm mt-1" role="group">
                                                                    <button type="button" 
                                                                            class="btn btn-outline-info btn-sm"
                                                                            onclick="forceUnexpiry({{ $item->id }})"
                                                                            title="Unexpire (BEWARE DUPLICATES)">
                                                                        <i class="bi bi-arrow-counterclockwise"></i> Unexpire
                                                                    </button>
                                                                    <button type="button" 
                                                                            class="btn btn-outline-{{ $isActive ? 'warning' : 'success' }} btn-sm"
                                                                            onclick="toggleSuspension({{ $item->id }}, {{ $item->isactive }})"
                                                                            title="{{ $newActiveStateStr }}">
                                                                        <i class="bi bi-toggle-{{ $isActive ? 'on' : 'off' }}"></i> {{ ucfirst($newActiveStateStr) }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @elseif($isExpired)
                                                            <div class="mb-2">
                                                                <span class="text-danger fw-bold">Expired:</span> 
                                                                {{ \Carbon\Carbon::parse($item->theexpiry)->format('j M Y') }}
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <small class="text-muted d-block mb-1">Add 12 months:</small>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" 
                                                                            class="btn btn-outline-primary btn-sm"
                                                                            onclick="add12MonthsFromToday({{ $item->id }})"
                                                                            title="From today">
                                                                        From Today
                                                                    </button>
                                                                    <button type="button" 
                                                                            class="btn btn-outline-primary btn-sm"
                                                                            onclick="add12MonthsFromExpiry({{ $item->id }})"
                                                                            title="From current expiry">
                                                                        From Expiry
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <button type="button" 
                                                                        class="btn btn-outline-info btn-sm"
                                                                        onclick="showCalendarPicker({{ $item->id }}, {{ $loop->parent->index }}_{{ $loop->index }})"
                                                                        title="Pick date">
                                                                    <i class="bi bi-calendar"></i> Pick Date
                                                                </button>
                                                                <div id="datepicker{{ $loop->parent->index }}_{{ $loop->index }}" style="display:none;"></div>
                                                            </div>
                                                            
                                                            <div>
                                                                <small class="text-muted d-block mb-1">Remove number (do both):</small>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" 
                                                                            class="btn btn-outline-danger btn-sm"
                                                                            onclick="forceExpiry({{ $item->id }})"
                                                                            title="Force expiry">
                                                                        Force Expiry
                                                                    </button>
                                                                    <button type="button" 
                                                                            class="btn btn-outline-warning btn-sm"
                                                                            onclick="toggleSuspension({{ $item->id }}, {{ $item->isactive }})"
                                                                            title="{{ $newActiveStateStr }}">
                                                                        {{ ucfirst($newActiveStateStr) }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="mb-2">
                                                                <span class="fw-bold">Expires:</span> 
                                                                {{ \Carbon\Carbon::parse($item->theexpiry)->format('j M Y') }}
                                                                {!! $isActiveText !!}
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <small class="text-muted d-block mb-1">Add 12 months:</small>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" 
                                                                            class="btn btn-outline-primary btn-sm"
                                                                            onclick="add12MonthsFromToday({{ $item->id }})"
                                                                            title="From today">
                                                                        From Today
                                                                    </button>
                                                                    <button type="button" 
                                                                            class="btn btn-outline-primary btn-sm"
                                                                            onclick="add12MonthsFromExpiry({{ $item->id }})"
                                                                            title="From current expiry">
                                                                        From Expiry
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <button type="button" 
                                                                        class="btn btn-outline-info btn-sm"
                                                                        onclick="showCalendarPicker({{ $item->id }}, {{ $loop->parent->index }}_{{ $loop->index }})"
                                                                        title="Pick date">
                                                                    <i class="bi bi-calendar"></i> Pick Date
                                                                </button>
                                                                <div id="datepicker{{ $loop->parent->index }}_{{ $loop->index }}" style="display:none;"></div>
                                                            </div>
                                                            
                                                            <div>
                                                                <small class="text-muted d-block mb-1">Remove number (do both):</small>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" 
                                                                            class="btn btn-outline-danger btn-sm"
                                                                            onclick="forceExpiry({{ $item->id }})"
                                                                            title="Force expiry">
                                                                        Force Expiry
                                                                    </button>
                                                                    <button type="button" 
                                                                            class="btn btn-outline-warning btn-sm"
                                                                            onclick="toggleSuspension({{ $item->id }}, {{ $item->isactive }})"
                                                                            title="{{ $newActiveStateStr }}">
                                                                        {{ ucfirst($newActiveStateStr) }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    
                                                    <td>
                                                        <small>{{ $item->forwarding_email ?? 'N/A' }}</small>
                                                    </td>
                                                    
                                                    <td>
                                                        <button type="button" 
                                                                class="btn btn-danger btn-sm w-100"
                                                                onclick="removeVirtualNumber({{ $item->id }}, '{{ $item->keyword }}')"
                                                                title="Remove this keyword">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <hr>
                        @endforeach
                    @endif
                    
                </div>
            </div>
            
        </div>
    </div>
</div>
