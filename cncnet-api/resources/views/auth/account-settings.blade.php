@extends('layouts.app')
@section('title', 'Account')
@section('feature-video', \App\URLHelper::getVideoUrlbyAbbrev('ra2'))
@section('feature-video-poster', \App\URLHelper::getVideoPosterUrlByAbbrev('ra2'))

@section('feature')
    <div class="feature pt-5 pb-5">
        <div class="container px-4 py-5 text-light">
            <div class="row flex-lg-row-reverse align-items-center g-5 py-5">
                <div class="col-12">
                    <h1 class="display-4 lh-1 mb-3 text-uppercase">
                        <strong class="fw-bold">Ladder</strong>
                        <span>Account Settings</span>
                    </h1>
                </div>
            </div>

            <div class="mini-breadcrumb d-none d-lg-flex">
                <div class="mini-breadcrumb-item">
                    <a href="/">
                        <span class="material-symbols-outlined">
                            home
                        </span>
                    </a>
                </div>
                <div class="mini-breadcrumb-item">
                    <a href="/account">
                        <span class="material-symbols-outlined icon pe-3">
                            person
                        </span>
                        {{ $user->name }}'s account
                    </a>
                </div>
                <div class="mini-breadcrumb-item">
                    <a href="#">
                        <span class="material-symbols-outlined icon pe-3">
                            settings
                        </span>
                        Account Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('breadcrumb')
    <nav aria-label="breadcrumb" class="breadcrumb-nav">
        <div class="container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="/">
                        <span class="material-symbols-outlined">
                            home
                        </span>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="/account">
                        <span class="material-symbols-outlined icon pe-3">
                            person
                        </span>
                        {{ $user->name }}'s account
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="#">
                        <span class="material-symbols-outlined icon pe-3">
                            settings
                        </span>
                        Account Settings
                    </a>
                </li>
            </ol>
        </div>
    </nav>
@endsection

@section('content')
    <section class="mt-4 pt-4">
        <div class="container">

            <div class="row">
                <div class="col-md-12">
                    @include('components.form-messages')
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <form method="POST" action="/account/settings" enctype="multipart/form-data">
                        {{ csrf_field() }}

                        {{-- TODO future functionality will use this value, no need to have users touch this yet
                    <input id="enableAnonymous" type="checkbox" name="enableAnonymous"  value="{{ $userSettings->enableAnonymous }}" @if ($userSettings->enableAnonymous) checked @endif />
                    <label for="enableAnonymous"> Enable Anonymity </label>
                --}}

                        @if ($user->getIsAllowedToUploadAvatar() == false)
                            <h4>Ladder Avatar Disabled</h4>
                        @else
                            <div class="form-group mb-5">
                                <h3>Ladder Avatar</h3>
                                <p>
                                    <strong>Recommended dimensions are 300x300. Max file size: 1mb.<br /> File types allowed: jpg, png, gif </strong>
                                </p>
                                <p>
                                    Avatars that are not deemed suitable by CnCNet will be removed without warning. <br />
                                    Inappropriate images and advertising is not allowed.
                                </p>

                                <div>
                                    @include('components.avatar', ['avatar' => $user->getUserAvatar()])
                                </div>

                                <label for="avatar">Upload an avatar</label>
                                <input type="file" id="avatar" name="avatar">

                                @if ($user->getUserAvatar())
                                    <br />
                                    <label>
                                        <input id="removeAvatar" type="checkbox" name="removeAvatar" />
                                        Remove avatar?
                                    </label>
                                @endif
                            </div>
                        @endif

                        <div class="form-group">
                            <h3>Ladder AI Match making preference</h3>
                            <p>
                                This preference is only for the {{ \App\Helpers\LeagueHelper::getLeagueNameByTier(2) }}.
                                If no matches are found after some time, would you like to match against an AI?
                            </p>
                            <p>
                                <label>
                                    <input id="matchAI" type="checkbox" name="matchAI" @if ($userSettings->match_ai) checked @endif />
                                    Enable Matches against AI
                                </label>
                            </p>
                        </div>

                        <div class="form-group mt-5 mb-5">
                            <div class="checkbox">
                                @if (isset($userSettings))
                                    <h3>Ladder Point Filter</h3>
                                    <p>
                                        <strong class="highlight">Advanced Players Only! Disabling this as a new player is strongly
                                            discouraged.
                                        </strong>
                                    </p>
                                    <p>
                                        Disabling the Point Filter will match you against any player on the ladder regardless of your rank. <br />
                                        Opponents will also need it disabled.
                                    </p>
                                    <p>
                                        <label>
                                            <input id="disablePointFilter" type="checkbox" name="disabledPointFilter"
                                                @if ($userSettings->disabledPointFilter) checked @endif />
                                            Disable Point Filter &amp; Match with anyone
                                        </label>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <h3>Social profiles</h3>
                            <p>
                                These will be shown on all your ladder profiles. Do not enter URLs.
                            </p>
                        </div>

                        <div class="form-group mt-2">
                            <label for="twitch">Twitch username, e.g. <strong>myTwitchUsername</strong></label>
                            <input id="twitch" type="text" class="form-control" name="twitch_profile" value="{{ $user->twitch_profile }}"
                                placeholder="Enter your Twitch username only" />
                        </div>

                        <div class="form-group mt-2">
                            <label for="discord">Discord username, E.g. user#9999</label>
                            <input id="discord" type="text" class="form-control" name="discord_profile" value="{{ $user->discord_profile }}"
                                placeholder="Enter your Discord username only" />
                        </div>

                        <div class="form-group mt-2">
                            <label for="youtube">YouTube channel name e.g. <strong>myYouTubeChannel</strong></label>
                            <input id="youtube" type="text" class="form-control" name="youtube_profile" value="{{ $user->youtube_profile }}"
                                placeholder="Enter your YouTube username only" />
                        </div>

                        <div class="form-group mt-2 mb-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
