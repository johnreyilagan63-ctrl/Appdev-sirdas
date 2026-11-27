@php $user = auth()->user(); @endphp
@include('partials.header')

@if (! $selected && ! $selectedRoom)
    <div class="page-head">
        <div>
            <p class="eyebrow">Reservations</p>
            <h1 class="page-title">Choose a Cottage and Room</h1>
            <p class="page-sub">Pick the {{ $type === 'rooms' ? 'room' : 'cottage' }} you want, then set your dates and guests.</p>
        </div>
    </div>

    <div class="tabs mb-2">
        <a class="tab{{ $type === 'cottages' ? ' is-active' : '' }}" href="/booking">Cottages</a>
        <a class="tab{{ $type === 'rooms' ? ' is-active' : '' }}" href="/booking?type=rooms">Rooms</a>
    </div>

    @if ($type === 'rooms')
        @if ($rooms->isEmpty())
            <div class="card empty-state">
                <h3>No rooms available</h3>
                <p>Please check back after the resort publishes its rooms.</p>
            </div>
        @else
            <div class="cottage-grid">
                @foreach ($rooms as $index => $room)
                    @php $photo = $room->photo ?: '/assets/img/fallback.svg'; @endphp
                    <article class="cottage-card reveal stagger-{{ ($index % 3) + 1 }}"
                             @if ($index >= 3) data-room-extra hidden @endif>
                        <div class="cottage-card__media">
                            <img src="{{ $photo }}" alt="{{ $room->name }}" loading="lazy">
                        </div>
                        <div class="cottage-card__body">
                            <h3>{{ $room->name }}</h3>
                            <p class="cottage-meta">{!! icon('pin') !!}<span>{{ $room->location ?: 'Oliyette Resort' }}</span></p>
                            <div class="rating-line">
                                <span>Up to {{ (int) $room->capacity }} guests</span>
                            </div>
                            <p class="price">{{ peso($room->rate_per_day) }}<small> / day</small></p>
                        </div>
                        <div class="cottage-card__foot">
                            <a class="btn btn-primary" href="/booking?room={{ (int) $room->id }}">Select Room</a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($rooms->count() > 3)
                <div class="cottage-more">
                    <button class="btn btn-ghost" type="button" data-room-more aria-expanded="false">
                        View more rooms
                    </button>
                </div>
            @endif
        @endif
    @else
        @if ($cottages->isEmpty())
            <div class="card empty-state">
                <h3>No cottages available</h3>
                <p>Please check back after the resort publishes its listings.</p>
            </div>
        @else
            <div class="cottage-grid">
                @foreach ($cottages as $index => $cottage)
                    @php
                        $photo = $cottage->photo ?: '/assets/img/fallback.svg';
                        $rating = (float) $cottage->avg_rating;
                        $reviewCount = (int) $cottage->review_count;
                    @endphp
                    <article class="cottage-card reveal stagger-{{ ($index % 3) + 1 }}"
                             @if ($index >= 3) data-cottage-extra hidden @endif>
                        <div class="cottage-card__media">
                            <img src="{{ $photo }}" alt="{{ $cottage->name }}" loading="lazy">
                        </div>
                        <div class="cottage-card__body">
                            <h3>{{ $cottage->name }}</h3>
                            <p class="cottage-meta">{!! icon('pin') !!}<span>{{ $cottage->location ?: 'Oliyette Resort' }}</span></p>
                            <div class="rating-line">
                                <span>Up to {{ (int) $cottage->capacity }} guests</span>
                                @if ($reviewCount > 0)
                                    {!! star_html($rating, $reviewCount) !!}
                                    <span>{{ number_format($rating, 1) }} ({{ $reviewCount }})</span>
                                @else
                                    <span>No reviews yet</span>
                                @endif
                            </div>
                            <p class="price">{{ peso($cottage->rate_per_day) }}<small> / day</small></p>
                        </div>
                        <div class="cottage-card__foot">
                            <a class="btn btn-primary" href="/booking?cottage={{ (int) $cottage->id }}">Select Cottage</a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($cottages->count() > 3)
                <div class="cottage-more">
                    <button class="btn btn-ghost" type="button" data-cottage-more aria-expanded="false">
                        View more cottages
                    </button>
                </div>
            @endif
        @endif
    @endif

@else
    @php
        $isRoom = $selectedRoom !== null;
        $item = $isRoom ? $selectedRoom : $selected;
        $photo = $item->photo ?: '/assets/img/fallback.svg';
        $rating = $isRoom ? 0 : (float) $item->avg_rating;
        $reviewCount = $isRoom ? 0 : (int) $item->review_count;
        $amenities = array_filter(array_map('trim', explode(',', (string) ($item->amenities ?? ''))));
    @endphp
    <div class="page-head">
        <div>
            <p class="eyebrow">Booking</p>
            <h1 class="page-title">Book {{ $item->name }}</h1>
            <p class="page-sub">Choose your dates and times, then review the summary before submitting.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="{{ $isRoom ? '/booking?type=rooms' : '/booking' }}">Change {{ $isRoom ? 'room' : 'cottage' }}</a>
    </div>

    <div class="booking-layout">
        <div class="panel reveal">
            <form id="bookingForm" class="form" method="post" action="/actions/book"
                  data-cottage-name="{{ $item->name }}"
                  data-item-label="{{ $isRoom ? 'Room' : 'Cottage' }}"
                  data-guest-name="{{ $user->first_name }} {{ $user->last_name }}"
                  data-rate="{{ $item->rate_per_day }}"
                  data-entrance-fee="{{ config('booking.entrance_fee_per_adult') }}"
                  data-photo="{{ $photo }}">
                @csrf
                @if ($isRoom)
                    <input type="hidden" name="room_id" value="{{ (int) $item->id }}">
                @else
                    <input type="hidden" name="cottage_id" value="{{ (int) $item->id }}">
                @endif

                <div class="form-grid">
                    <div class="field">
                        <label for="check_in">Check-in Date</label>
                        <input type="date" id="check_in" name="check_in" required>
                    </div>
                    <div class="field">
                        <label for="check_out">Check-out Date</label>
                        <input type="date" id="check_out" name="check_out" required>
                    </div>
                </div>

                <section class="booking-calendar" data-booking-calendar aria-label="Booking availability calendar">
                    <div class="booking-calendar__top">
                        <div>
                            <p class="eyebrow">Availability</p>
                            <h2 class="booking-calendar__month" id="bookingCalendarMonth"></h2>
                        </div>
                        <div class="booking-calendar__nav">
                            <button class="btn btn-ghost btn-sm" type="button" data-calendar-prev aria-label="Previous month">Prev</button>
                            <button class="btn btn-ghost btn-sm" type="button" data-calendar-next aria-label="Next month">Next</button>
                        </div>
                    </div>
                    <div class="booking-calendar__weekdays" aria-hidden="true">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>
                        <span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>
                    <div class="booking-calendar__days" id="bookingCalendarDays"></div>
                    <div class="booking-calendar__legend" aria-hidden="true">
                        <span><i class="booking-calendar__swatch"></i>Available</span>
                        <span><i class="booking-calendar__swatch is-reserved"></i>Reserved</span>
                        <span><i class="booking-calendar__swatch is-selected"></i>Your stay</span>
                    </div>
                    <p class="helper" id="bookingCalendarHelp" aria-live="polite">Choose a check-in date, then a check-out date. Past and reserved dates are unavailable.</p>
                </section>

                <div class="form-grid">
                    <div class="field">
                        <label for="check_in_time">Check-in Time</label>
                        <input type="time" id="check_in_time" name="check_in_time" value="14:00" required>
                    </div>
                    <div class="field">
                        <label for="check_out_time">Check-out Time</label>
                        <input type="time" id="check_out_time" name="check_out_time" value="12:00" required>
                    </div>
                </div>

                <p class="helper" id="sameDayNote" hidden></p>
                <p class="field-error" id="dateAvailability" hidden></p>
                <p class="helper">Each cottage and room accepts one active booking per date. Reserved dates, including the check-out date, cannot be booked by another guest.</p>

                <div class="form-grid">
                    <div class="field">
                        <label for="adults">Adults</label>
                        <input type="number" id="adults" name="adults" min="1" max="20" value="1" required>
                    </div>
                    <div class="field">
                        <label for="children">Children</label>
                        <input type="number" id="children" name="children" min="0" max="20" value="0" required>
                    </div>
                </div>

                <p class="helper">Payment is made at the resort when you arrive.</p>

                <div class="field">
                    <label for="special_requests">Special Requests</label>
                    <textarea id="special_requests" name="special_requests"
                              placeholder="Anything the resort should know before you arrive?"></textarea>
                </div>

                <p class="helper">The {{ $isRoom ? 'room' : 'cottage' }} rate is {{ peso($item->rate_per_day) }} per night. An entrance fee of {{ peso(config('booking.entrance_fee_per_adult')) }} per adult is added to the total.</p>

                @if ($unavailable)
                    <div class="card" style="box-shadow:none;border:1px dashed var(--color-border);padding:14px;">
                        <strong class="text-muted" style="font-size:0.85rem;">Already reserved dates</strong>
                        <ul class="helper mt-1" style="display:grid;gap:4px;">
                            @foreach ($unavailable as $range)
                                <li>{{ format_date($range['check_in']) }} {{ format_time($range['check_in_time']) }} — {{ format_date($range['check_out']) }} {{ format_time($range['check_out_time']) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <button class="btn btn-primary btn-lg" type="submit">Review Booking</button>
                <p class="helper">Your booking becomes <strong>Pending</strong> until the admin approves it.</p>
            </form>
        </div>

        <aside class="panel reveal stagger-2">
            <img src="{{ $photo }}" alt="{{ $item->name }}" class="modal-photo">
            <h2>{{ $item->name }}</h2>
            <p class="cottage-meta">{!! icon('pin') !!}<span>{{ $item->location ?: 'Oliyette Resort' }}</span></p>

            @if ($reviewCount > 0)
                <div class="rating-line mt-1">
                    {!! star_html($rating, $reviewCount) !!}
                    <span>{{ number_format($rating, 1) }} ({{ $reviewCount }} reviews)</span>
                </div>
            @else
                <p class="text-muted mt-1">No reviews yet</p>
            @endif

            <p class="price mt-2">{{ peso($item->rate_per_day) }}<small> / day</small></p>
            <p><strong>Capacity:</strong> {{ (int) $item->capacity }} guests</p>

            @if (! empty($item->description))
                <p class="text-muted">{{ $item->description }}</p>
            @endif

            @if ($isRoom && $amenities)
                <h3 class="mt-2" style="font-size:1rem;">Amenities</h3>
                <ul class="amenity-list">
                    @foreach ($amenities as $amenity)
                        <li>{!! icon('check') !!}<span>{{ $amenity }}</span></li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>

    <script type="application/json" id="unavailableDates">@json($unavailable)</script>

    @push('modals')
    <div class="modal" id="bookingSummary" data-modal hidden>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="summaryTitle">
            <div class="modal__head">
                <h2 id="summaryTitle">Booking Summary</h2>
                <button class="modal-close" type="button" data-modal-close aria-label="Close summary">
                    {!! icon('close') !!}
                </button>
            </div>
            <div class="modal__body">
                <img id="summaryPhoto" class="modal-photo" src="{{ $photo }}" alt="{{ $item->name }}">
                <div id="summaryBody"></div>
                <p class="modal-note">Check the details above. After you submit, the resort admin will review and approve your reservation.</p>
            </div>
            <div class="modal__foot">
                <button class="btn btn-secondary" type="button" data-modal-close>Edit</button>
                <button class="btn btn-primary" type="button" id="summaryConfirm">Confirm &amp; Submit</button>
            </div>
        </div>
    </div>
    @endpush
@endif

@include('partials.footer')
