@extends('layouts.app')
@section('title', 'Account')

@section('cover')
/images/feature/feature-td.jpg
@endsection

@section('feature')
<div class="feature-background sub-feature-background">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-8 col-md-offset-2">
                <h1>
                    CnCNet Ladder Account
                </h1>
                <p class="text-uppercase">
                   Play. Compete. <strong>Conquer.</strong>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<section>
    <div class="container">
        <div class="feature">
            <div class="row">
                <div class="col-md-12">
                    <div class="text-center" style="padding-bottom: 40px;">
                        <h1>Hi {{ $user->name }} </h1>
                        <p class="lead">Manage everything to do with your CnCNet Ladder Account here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<style>
.tutorial {     
    background: #074b85;
    padding: 15px;
    color: white; 
}
</style>
<section class="cncnet-features dark-texture">
    <div class="container">

        <div class="row">
            <div class="col-md-4 @if(Input::get('tutorial'))tutorial @endif">
                <h2>@if(Input::get('tutorial')) <strong>Step 2</strong> - @endif Add a new username?</h2>

                @include("components.form-messages")

                <form method="POST" action="account/username">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <div class="form-group">
                        <p>Usernames will be the name shown when you login to CnCNet clients and play games.</p>
                        <label for="username">Username</label>
                        <input type="text" name="username" class="form-control" id="username" placeholder="Username">
                    </div>
                    <div class="form-group">
                        <label for="ladder">Ladder</label>
                        <select name="ladder" id="ladder" class="form-control">
                        @foreach($ladders as $history)
                        <option value="{{ $history->ladder->id }}">{{ $history->ladder->name }}</option>
                        @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">Create</button>
                </form>
            </div>
            <div class="col-md-6 col-md-offset-2">
                <h2>Your Usernames</h2>

                <div class="table-responsive">
                    <table class="table table-hover player-games">
                        <thead>
                            <tr>
                                <th>Username <i class="fa fa-user-o fa-fw"></i></th>
                                <th>Ladder <i class="fa fa-trophy fa-fw"></i></th>
                                <th>Player Card</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $cards = \App\Card::all(); ?>
                            @foreach($user->usernames()->get() as $u)
                            <tr>
                                <td>{{ $u->username }}</td>
                                <td>
                                @if(isset($u->ladder()->first()->abbreviation))
                                    {{ $u->ladder()->first()->name }}
                                @endif
                                </td>
                                <td>
                                    <form class="form-inline" method="POST" action="account/card">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="hidden" name="playerId" value="{{ $u->id }}">
                                        <select class="form-control" name="cardId">
                                            @foreach($cards as $card)
                                                <option value="{{ $card->id }}" @if($card->id == $u->card_id) selected @endif>
                                                {{ $card->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-secondary">Save</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

