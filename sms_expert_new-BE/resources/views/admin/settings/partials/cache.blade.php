<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0"><i class="material-icons-outlined font-18 me-1">cached</i> Cache Management</h5>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="material-icons-outlined font-18 me-1">check_circle</i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="material-icons-outlined font-18 me-1">error</i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Clear All Caches Section -->
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0" style="color: white">
                    <i class="material-icons-outlined font-18 me-1">cleaning_services</i>
                    Clear All Caches
                </h6>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    Clear all application caches at once. This includes application cache, configuration, routes, views,
                    events, and compiled classes.
                </p>
                <form action="{{ route('admin.cache.clear-all') }}" method="POST"
                    onsubmit="return confirm('Are you sure you want to clear all caches?');">
                    @csrf
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-small text-white">
                            <i class="material-icons-outlined font-18 me-1" style="color: white;">delete_sweep</i>
                            Clear All Caches
                        </button>
                    </div>
                </form>
            </div>

            {{-- <div class="card-body">
                <p class="mb-3">Clear all application caches at once. This includes application cache, configuration, routes, views, events, and compiled classes.</p>
                <form action="{{ route('admin.cache.clear-all') }}" method="POST" onsubmit="return confirm('Are you sure you want to clear all caches?');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-small">
                        <i class="material-icons-outlined font-18 me-1">delete_sweep</i>
                        Clear All Caches
                    </button>
                </form>
            </div> --}}
        </div>

        <!-- Individual Cache Controls -->
        <div class="row g-4">
            <!-- Application Cache -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-title bg-soft-primary text-primary rounded-circle fs-3">
                                    <i class="material-icons-outlined">memory</i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Application Cache</h5>
                                <p class="text-muted mb-0 small">Runtime data cache</p>
                            </div>
                        </div>
                        <p class="card-text small mb-3">Clears all application cache data stored by your Laravel
                            application.</p>
                        <form action="{{ route('admin.cache.clear-app') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="material-icons-outlined font-16">delete</i>
                                Clear Cache
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Configuration Cache -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-title bg-soft-success text-success rounded-circle fs-3">
                                    <i class="material-icons-outlined">settings_applications</i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Config Cache</h5>
                                <p class="text-muted mb-0 small">Configuration files</p>
                            </div>
                        </div>
                        <p class="card-text small mb-3">Clears cached configuration files. Use after changing .env or
                            config files.</p>
                        <div class="btn-group w-100" role="group">
                            <form action="{{ route('admin.cache.clear-config') }}" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="material-icons-outlined font-16">delete</i>
                                    Clear
                                </button>
                            </form>&nbsp;
                            <form action="{{ route('admin.cache.cache-config') }}" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                    <i class="material-icons-outlined font-16">save</i>
                                    Cache
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Route Cache -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-title bg-soft-info text-info rounded-circle fs-3">
                                    <i class="material-icons-outlined">route</i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Route Cache</h5>
                                <p class="text-muted mb-0 small">Application routes</p>
                            </div>
                        </div>
                        <p class="card-text small mb-3">Clears cached routes. Use after adding or modifying routes in
                            your application.</p>
                        <div class="btn-group w-100" role="group">
                            <form action="{{ route('admin.cache.clear-route') }}" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="material-icons-outlined font-16">delete</i>
                                    Clear
                                </button>
                            </form>&nbsp;
                            <form action="{{ route('admin.cache.cache-route') }}" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                    <i class="material-icons-outlined font-16">save</i>
                                    Cache
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Cache -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-title bg-soft-warning text-warning rounded-circle fs-3">
                                    <i class="material-icons-outlined">visibility</i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">View Cache</h5>
                                <p class="text-muted mb-0 small">Compiled Blade views</p>
                            </div>
                        </div>
                        <p class="card-text small mb-3">Clears compiled view files. Use after modifying Blade
                            templates.</p>
                        <div class="btn-group w-100" role="group">
                            <form action="{{ route('admin.cache.clear-view') }}" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="material-icons-outlined font-16">delete</i>
                                    Clear
                                </button>
                            </form>&nbsp;
                            <form action="{{ route('admin.cache.cache-view') }}" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                    <i class="material-icons-outlined font-16">save</i>
                                    Cache
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event Cache -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-title bg-soft-danger text-danger rounded-circle fs-3">
                                    <i class="material-icons-outlined">event</i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Event Cache</h5>
                                <p class="text-muted mb-0 small">Cached events</p>
                            </div>
                        </div>
                        <p class="card-text small mb-3">Clears cached events and listeners. Use after modifying event
                            listeners.</p>
                        <form action="{{ route('admin.cache.clear-event') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="material-icons-outlined font-16">delete</i>
                                Clear Cache
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Compiled Classes -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-title bg-soft-secondary text-secondary rounded-circle fs-3">
                                    <i class="material-icons-outlined">code</i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">Compiled Classes</h5>
                                <p class="text-muted mb-0 small">Bootstrap cache</p>
                            </div>
                        </div>
                        <p class="card-text small mb-3">Removes compiled class files. Helps resolve bootstrap-related
                            issues.</p>
                        <form action="{{ route('admin.cache.clear-compiled') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="material-icons-outlined font-16">delete</i>
                                Clear Compiled
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Optimization Section -->
        <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="material-icons-outlined font-18 me-1">speed</i>
                    Application Optimization
                </h6>
            </div>
            <div class="card-body">
                <p class="mb-3">Optimize the application for better performance. This caches configuration, routes,
                    and events.</p>
                <form action="{{ route('admin.cache.optimize') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="material-icons-outlined font-18 me-1">rocket_launch</i>
                        Optimize Application
                    </button>
                </form>
            </div>
        </div>

        <!-- Information Card -->
        <div class="alert alert-info mt-4">
            <h6 class="alert-heading">
                <i class="material-icons-outlined font-18 me-1">info</i>
                Cache Information
            </h6>
            <ul class="mb-0 small">
                <li><strong>Application Cache:</strong> Stores runtime data like database query results</li>
                <li><strong>Config Cache:</strong> Improves performance by caching configuration files</li>
                <li><strong>Route Cache:</strong> Speeds up route registration significantly</li>
                <li><strong>View Cache:</strong> Stores compiled Blade templates</li>
                <li><strong>Event Cache:</strong> Caches event-to-listener mappings</li>
                <li><strong>Optimize:</strong> Runs multiple optimization commands at once</li>
            </ul>
        </div>

        <!-- Best Practices -->
        <div class="alert alert-warning mt-3">
            <h6 class="alert-heading">
                <i class="material-icons-outlined font-18 me-1">lightbulb</i>
                Best Practices
            </h6>
            <ul class="mb-0 small">
                <li>Clear caches after updating .env file or configuration</li>
                <li>Clear view cache after modifying Blade templates</li>
                <li>Clear route cache after adding new routes</li>
                <li>Use "Clear All Caches" when troubleshooting issues</li>
                <li>Cache configuration and routes in production for better performance</li>
                <li>Don't cache routes if using Closure-based routes</li>
            </ul>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .avatar-sm {
            height: 3rem;
            width: 3rem;
        }

        .avatar-title {
            align-items: center;
            display: flex;
            height: 100%;
            justify-content: center;
            width: 100%;
        }

        .bg-soft-primary {
            background-color: rgba(13, 110, 253, 0.1) !important;
        }

        .bg-soft-success {
            background-color: rgba(25, 135, 84, 0.1) !important;
        }

        .bg-soft-info {
            background-color: rgba(13, 202, 240, 0.1) !important;
        }

        .bg-soft-warning {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }

        .bg-soft-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .bg-soft-secondary {
            background-color: rgba(108, 117, 125, 0.1) !important;
        }

        .card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
@endpush
