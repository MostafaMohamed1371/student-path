@if(($showSchoolFilter ?? false) || ($showDriverFilter ?? false) || ($showShiftFilter ?? false) || ($showStudentFilter ?? false) || ($showGuardianFilter ?? false) || ($showUserRoleFilter ?? false) || ($showTripTypeFilter ?? false) || ($showNotificationTypeFilter ?? false) || ($showUnreadFilter ?? false))
    <section class="card" style="margin-bottom:16px;">
        <form method="get" action="{{ $filterAction }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            @foreach(request()->except(['school_id', 'driver_id', 'shift_period', 'student_id', 'guardian_id', 'user_role', 'trip_type', 'notification_type', 'unread_only', 'page']) as $key => $value)
                @if(is_scalar($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            @if($showSchoolFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.school') }}</span>
                    <select class="input" name="school_id" style="min-width:200px;">
                        <option value="0" @selected(($filterSchoolId ?? 0) === 0)>{{ __('dashboard.report_filter_all_schools') }}</option>
                        @foreach($schools ?? [] as $school)
                            <option value="{{ $school->id }}" @selected(($filterSchoolId ?? 0) === (int) $school->id)>{{ $school->name_en }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif(($schools ?? collect())->isNotEmpty())
                <label>
                    <span class="field-label">{{ __('dashboard.school') }}</span>
                    <select class="input" disabled style="min-width:200px;">
                        <option>{{ $schools->first()->name_en }}</option>
                    </select>
                </label>
            @endif
            @if($showDriverFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.driver') }}</span>
                    <select class="input" name="driver_id" style="min-width:200px;">
                        <option value="0" @selected(($filterDriverId ?? 0) === 0)>{{ __('dashboard.report_filter_all_drivers') }}</option>
                        @foreach($drivers ?? [] as $driver)
                            @php($driverName = trim(($driver->first_name ?? '').' '.($driver->last_name ?? '')) ?: '#'.$driver->id)
                            <option value="{{ $driver->id }}" @selected(($filterDriverId ?? 0) === (int) $driver->id)>{{ $driverName }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if($showShiftFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.shift_period') }}</span>
                    <select class="input" name="shift_period" style="min-width:200px;">
                        <option value="" @selected(($filterShiftPeriod ?? '') === '')>{{ __('dashboard.report_filter_all_shifts') }}</option>
                        <option value="MORNING" @selected(($filterShiftPeriod ?? '') === 'MORNING')>{{ __('dashboard.shift_period_morning') }}</option>
                        <option value="EVENING" @selected(($filterShiftPeriod ?? '') === 'EVENING')>{{ __('dashboard.shift_period_evening') }}</option>
                        <option value="BOTH" @selected(($filterShiftPeriod ?? '') === 'BOTH')>{{ __('dashboard.shift_period_both') }}</option>
                    </select>
                </label>
            @endif
            @if($showStudentFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.menu_students') }}</span>
                    <select class="input" name="student_id" style="min-width:220px;">
                        <option value="0" @selected(($filterStudentId ?? 0) === 0)>{{ __('dashboard.report_filter_all_students') }}</option>
                        @foreach($students ?? [] as $student)
                            @php($studentLabel = trim((string) $student->full_name).' — '.trim((string) $student->grade).' #'.$student->id)
                            <option value="{{ $student->id }}" @selected(($filterStudentId ?? 0) === (int) $student->id)>{{ $studentLabel }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if($showGuardianFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.menu_guardians') }}</span>
                    <select class="input" name="guardian_id" style="min-width:220px;">
                        <option value="0" @selected(($filterGuardianId ?? 0) === 0)>{{ __('dashboard.report_filter_all_guardians') }}</option>
                        @foreach($guardians ?? [] as $guardian)
                            @php($guardianLabel = trim((string) $guardian->full_name).' — '.trim((string) $guardian->phone).' #'.$guardian->id)
                            <option value="{{ $guardian->id }}" @selected(($filterGuardianId ?? 0) === (int) $guardian->id)>{{ $guardianLabel }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if($showUserRoleFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.table_col_type') }}</span>
                    <select class="input" name="user_role" style="min-width:180px;">
                        <option value="" @selected(($filterUserRole ?? '') === '')>{{ __('dashboard.report_filter_all_roles') }}</option>
                        <option value="admin" @selected(($filterUserRole ?? '') === 'admin')>{{ __('dashboard.admin') }}</option>
                        <option value="driver" @selected(($filterUserRole ?? '') === 'driver')>{{ __('dashboard.driver') }}</option>
                        <option value="guardian" @selected(($filterUserRole ?? '') === 'guardian')>{{ __('dashboard.menu_guardians') }}</option>
                        <option value="student" @selected(($filterUserRole ?? '') === 'student')>{{ __('dashboard.menu_students') }}</option>
                    </select>
                </label>
            @endif
            @if($showTripTypeFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.trip_field_type') }}</span>
                    <select class="input" name="trip_type" style="min-width:200px;">
                        <option value="" @selected(($filterTripType ?? '') === '')>{{ __('dashboard.report_filter_all_trip_types') }}</option>
                        @foreach($tripTypeOptions ?? [] as $tt)
                            <option value="{{ $tt }}" @selected(($filterTripType ?? '') === $tt)>{{ $tt }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if($showNotificationTypeFilter ?? false)
                <label>
                    <span class="field-label">{{ __('dashboard.table_col_notification_type') }}</span>
                    <select class="input" name="notification_type" style="min-width:220px;">
                        <option value="" @selected(($filterNotificationType ?? '') === '')>{{ __('dashboard.report_filter_all_notification_types') }}</option>
                        @foreach($notificationTypeOptions ?? [] as $nt)
                            <option value="{{ $nt }}" @selected(($filterNotificationType ?? '') === $nt)>{{ \App\Support\Dashboard\InAppNotificationPresenter::typeLabel($nt) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if($showUnreadFilter ?? false)
                <label style="display:flex;align-items:center;gap:8px;padding-bottom:6px;">
                    <input type="checkbox" name="unread_only" value="1" @checked($filterUnreadOnly ?? false)>
                    <span class="field-label" style="margin:0;">{{ __('dashboard.report_filter_unread_only') }}</span>
                </label>
            @endif
            <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.filter') }}</button>
        </form>
    </section>
@endif
