@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>{{ __('My Activity History') }}</h4>
                    <small class="text-muted">Login and logout activities for your account</small>
                </div>

                <div class="card-body">
                    @if($activities->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Activity</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                        <th>Browser</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activities as $activity)
                                        <tr>
                                            <td>
                                                <strong>{{ $activity->activity_at->format('M d, Y') }}</strong><br>
                                                <small class="text-muted">{{ $activity->activity_at->format('H:i:s') }}</small>
                                            </td>
                                            <td>
                                                @if($activity->activity_type === 'login')
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-sign-in-alt"></i> Login
                                                    </span>
                                                @elseif($activity->activity_type === 'logout')
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-sign-out-alt"></i> Logout
                                                    </span>
                                                @elseif($activity->activity_type === 'session_timeout')
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock"></i> Timeout
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary">
                                                        {{ ucfirst($activity->activity_type) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->logout_reason)
                                                    <small class="text-muted">
                                                        @switch($activity->logout_reason)
                                                            @case('tab_close')
                                                                Tab closed
                                                                @break
                                                            @case('browser_close')
                                                                Browser closed
                                                                @break
                                                            @case('manual_logout')
                                                                Manual logout
                                                                @break
                                                            @case('inactivity_timeout')
                                                                Session expired
                                                                @break
                                                            @case('concurrent_session')
                                                                Another login detected
                                                                @break
                                                            @default
                                                                {{ ucfirst(str_replace('_', ' ', $activity->logout_reason)) }}
                                                        @endswitch
                                                    </small>
                                                @else
                                                    @if($activity->additional_data && isset($activity->additional_data['remember_me']) && $activity->additional_data['remember_me'])
                                                        <small class="text-muted">Remember me enabled</small>
                                                    @else
                                                        -
                                                    @endif
                                                @endif
                                                
                                                @if($activity->additional_data && isset($activity->additional_data['session_duration_minutes']))
                                                    <br><small class="text-info">
                                                        Session: {{ $activity->additional_data['session_duration_minutes'] }} minutes
                                                    </small>
                                                @endif
                                            </td>
                                            <td>
                                                <code>{{ $activity->ip_address }}</code>
                                            </td>
                                            <td>
                                                <small class="text-muted" title="{{ $activity->user_agent }}">
                                                    @php
                                                        $browser = 'Unknown';
                                                        if (str_contains($activity->user_agent, 'Chrome')) {
                                                            $browser = 'Chrome';
                                                        } elseif (str_contains($activity->user_agent, 'Firefox')) {
                                                            $browser = 'Firefox';
                                                        } elseif (str_contains($activity->user_agent, 'Safari')) {
                                                            $browser = 'Safari';
                                                        } elseif (str_contains($activity->user_agent, 'Edge')) {
                                                            $browser = 'Edge';
                                                        }
                                                    @endphp
                                                    {{ $browser }}
                                                </small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Showing last {{ $activities->count() }} activities. 
                                Activities older than 90 days are automatically removed.
                            </small>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5>No Activity History</h5>
                            <p class="text-muted">No login or logout activities recorded yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection