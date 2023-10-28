@extends('layouts.app')
@section('css')
<style>
.banner {
  padding: 0;
  background-color: #52575c;
  color: white;
}

.banner-text {
  padding: 8px 20px;
  margin: 0;
}


#join-form {
  margin-top: 10px;
}

.tips {
  font-size: 12px;
  margin-bottom: 2px;
  color: gray;
}

.join-info-text {
  margin-bottom: 2px;
}

input {
  width: 100%;
  margin-bottom: 2px;
}

.player {
  width: 480px;
  height: 320px;
}

.player-name {
  margin: 8px 0;
}

#success-alert, #success-alert-with-token {
  display: none;
}

@media (max-width: 640px) {
  .player {
    width: 320px;
    height: 240px;
  }
}

</style>
@endsection



@section('content')
<div class="container-fluid banner">
    <p class="banner-text">Basic Video Call</p>
    <!-- Your banner content -->
</div>

<div id="success-alert" class="alert alert-success alert-dismissible fade show" role="alert">
    <!-- Your success alert content -->
</div>

<div class="container">
    <form id="join-form">
        <div class="row join-info-group">
           
            <div class="col-sm">
                <p class="join-info-text">Channel</p>
                <input id="channel" type="text" placeholder="enter channel name" required>
                <p class="tips">If you don't know what your channel is, check <a href="https://docs.agora.io/en/Agora%20Platform/terms?platform=All%20Platforms#channel">this</a></p>
            </div>
        </div>

        <div class="button-group">
            <button id="join" type="submit" class="btn btn-primary btn-sm">Join</button>
            <button id="leave" type="button" class="btn btn-primary btn-sm" disabled>Leave</button>
            <button id="show-video" type="button" class="btn btn-primary btn-sm" style="display: none">Show Video</button>
            <button id="mute-audio" type="button" class="btn btn-primary btn-sm" style="display: none">Mute Audio</button>
        </div>
    </form>

    <div class="row video-group">
        <div class="col">
            <p id="local-player-name" class="player-name"></p>
            <div id="local-player" class="player"></div>
        </div>
        <div class="w-100"></div>
        <div class="col">
            <div id="remote-playerlist"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Create Agora client
    var client = AgoraRTC.createClient({ mode: "rtc", codec: "vp8" });

    var localTracks = {
        videoTrack: null,
        audioTrack: null
    };
    var remoteUsers = {};
    // Agora client options
    var options = {
        appid: '{{ env('AGORA_APP_ID') }}',
        channel: null,
        uid: null,
        token: null
    };
 var videoTrackEnabled = true;
    var audioTrackEnabled = true;
    // Access the user's role from your backend and store it in a variable
    var userRole = {{ auth()->user()->role }};

    // Add a click event listener to the "Join" button
    $("#join").click(async function (e) {
        e.preventDefault();
        $("#join").attr("disabled", true);
        try {
            options.channel = $("#channel").val();
            await join();
        } catch (error) {
            console.error(error);
        } finally {
            $("#leave").attr("disabled", false);
        }
    });
    
        $("#show-video").click(function () {
        toggleVideo();
    });

    // Add a click event listener to the "Mute Audio" button
    $("#mute-audio").click(function () {
        toggleAudio();
    });

    $("#leave").click(function (e) {
        leave();
    });

    async function join() {
    // Add event listener to play remote tracks when remote user publishes.
    client.on("user-published", handleUserPublished);
    client.on("user-unpublished", handleUserUnpublished);

    // Join a channel and create local tracks, use Promise.all to run them concurrently
    [options.uid, localTracks.audioTrack, localTracks.videoTrack] = await Promise.all([
        // Join the channel
        client.join(options.appid, options.channel, options.token || null),
        // Create local tracks, use microphone and camera
        AgoraRTC.createMicrophoneAudioTrack(),
        AgoraRTC.createCameraVideoTrack()
    ]);

    // Play the local video track if the user is a host
    if (userRole === 1) {
        localTracks.videoTrack.play("local-player");
        await client.publish(Object.values(localTracks)); // Publish both audio and video for the host
        $("#show-video").show();
        $("#mute-audio").show();
    } else {
        // If the user is not the host, only publish audio track
        await client.publish(localTracks.audioTrack);
        $("#mute-audio").show();
    }
}

    async function leave() {
        for (trackName in localTracks) {
            var track = localTracks[trackName];
            if (track) {
                track.stop();
                track.close();
                localTracks[trackName] = undefined;
            }
        }

        // Remove remote users and player views
        remoteUsers = {};
        $("#remote-playerlist").html("");

        // Leave the channel
        await client.leave();

        $("#local-player-name").text("");
        $("#join").attr("disabled", false);
        $("#leave").attr("disabled", true);
         $("#show-video").hide();
        $("#mute-audio").hide();
    }

    async function subscribe(user, mediaType) {
        const uid = user.uid;
        // Subscribe to a remote user
        await client.subscribe(user, mediaType);
        console.log("Subscribe success");
        if (mediaType === 'video') {
            const player = $(`
                <div id="player-wrapper-${uid}">
                    <p class="player-name">remoteUser(${uid})</p>
                    <div id="player-${uid}" class="player"></div>
                </div>
            `);
            $("#remote-playerlist").append(player);
            user.videoTrack.play(`player-${uid}`);
        }
        if (mediaType === 'audio') {
            user.audioTrack.play();
        }
    }

    function handleUserPublished(user, mediaType) {
        const id = user.uid;
        remoteUsers[id] = user;
        subscribe(user, mediaType);
    }

    function handleUserUnpublished(user) {
        const id = user.uid;
        delete remoteUsers[id];
        $(`#player-wrapper-${id}`).remove();
    }
    function toggleMute() {
    if (userRole === 1) {
        localTracks.audioTrack.setEnabled(!audioTrackEnabled);
        $("#mute-audio").text(audioTrackEnabled ? "Mute Audio" : "Unmute Audio");
    }
}

// Add a click event listener to the "Mute Audio" button (for the host)
$("#mute-audio").click(function () {
    toggleMute();
});
    
      function toggleVideo() {
        if (localTracks.videoTrack) {
            videoTrackEnabled = !videoTrackEnabled;
            localTracks.videoTrack.setEnabled(videoTrackEnabled);
            $("#show-video").text(videoTrackEnabled ? "Hide Video" : "Show Video");
        }
    }

    function toggleAudio() {
        if (localTracks.audioTrack) {
            audioTrackEnabled = !audioTrackEnabled;
            localTracks.audioTrack.setEnabled(audioTrackEnabled);
            $("#mute-audio").text(audioTrackEnabled ? "Mute Audio" : "Unmute Audio");
        }
    }
    

 
</script>

@endsection