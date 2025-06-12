<?php

namespace Tests\Http\Controllers\Api\Entries\View\Internal;

use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Http\Controllers\Api\Entries\View\ViewEntriesBaseControllerTest;
use Throwable;

class MediaControllerTest extends ViewEntriesBaseControllerTest
{
    use DatabaseTransactions;
    use Assertions;

    /**
     * @throws Throwable
     */
    public function test_parent_entry_audio_is_playable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake audio to the entry, with 2KB size to make the range header work
        $entryUuid = $entryPayload['data']['id'];
        $audioFilename = $entryUuid. '_' . time() . '.mp4';
        Storage::disk('audio')->put($this->project->ref . '/' . $audioFilename, str_repeat('A', 2048));

        //try to get the audio file using a range request to get 206 response
        $queryString = '?type=audio&name=' . $audioFilename . '&format=audio';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->withHeaders([
                    'Range' => 'bytes=0-10'
                ])
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(206);

            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_parent_entry_audio_is_downloadable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake audio to the entry, with 2KB size
        $entryUuid = $entryPayload['data']['id'];
        $audioFilename = $entryUuid. '_' . time() . '.mp4';
        $audioContent = str_repeat('A', 2048);
        Storage::disk('audio')->put($this->project->ref . '/' . $audioFilename, $audioContent);

        //try to get the audio file
        $queryString = '?type=audio&name=' . $audioFilename . '&format=audio';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            // Assert headers
            $response[0]->assertHeader('Content-Type', 'audio/mp4');
            $response[0]->assertHeader('Content-Length', (string) strlen($audioContent));
            $response[0]->assertHeader('Accept-Ranges', 'bytes');

            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_parent_entry_video_is_playable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake video to the entry, with 2KB size to make the range header work
        $entryUuid = $entryPayload['data']['id'];
        $videoFilename = $entryUuid. '_' . time() . '.mp4';
        Storage::disk('video')->put($this->project->ref . '/' . $videoFilename, str_repeat('A', 2048));

        //try to get the video file using a range request to get 206 response
        $queryString = '?type=video&name=' . $videoFilename . '&format=video';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->withHeaders([
                    'Range' => 'bytes=0-10'
                ])
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(206);

            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_parent_entry_video_is_downloadable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake video to the entry, with 2KB size
        $entryUuid = $entryPayload['data']['id'];
        $videoFilename = $entryUuid. '_' . time() . '.mp4';
        $videoContent = str_repeat('A', 2048);
        Storage::disk('video')->put($this->project->ref . '/' . $videoFilename, $videoContent);

        //try to get the video file
        $queryString = '?type=video&name=' . $videoFilename . '&format=video';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            // Assert headers
            $response[0]->assertHeader('Content-Type', 'video/mp4');
            $response[0]->assertHeader('Content-Length', (string) strlen($videoContent));
            $response[0]->assertHeader('Accept-Ranges', 'bytes');


            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
