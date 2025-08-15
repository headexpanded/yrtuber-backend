<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\VideoEnhancementService;
use Illuminate\Console\Command;

class EnhanceVideoMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:enhance
                            {--video-id= : Specific video ID to enhance}
                            {--youtube-id= : YouTube ID to enhance}
                            {--all : Enhance all videos}
                            {--batch=50 : Number of videos to process in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enhance video metadata from YouTube API';

    private VideoEnhancementService $enhancementService;

    public function __construct(VideoEnhancementService $enhancementService)
    {
        parent::__construct();
        $this->enhancementService = $enhancementService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $videoId = $this->option('video-id');
        $youtubeId = $this->option('youtube-id');
        $all = $this->option('all');
        $batchSize = (int) $this->option('batch');

        if (!$videoId && !$youtubeId && !$all) {
            $this->error('Please specify --video-id, --youtube-id, or --all option.');
            return 1;
        }

        if ($videoId) {
            return $this->enhanceSingleVideo($videoId);
        }

        if ($youtubeId) {
            return $this->enhanceByYoutubeId($youtubeId);
        }

        if ($all) {
            return $this->enhanceAllVideos($batchSize);
        }

        return 0;
    }

    private function enhanceSingleVideo(int $videoId): int
    {
        $video = Video::find($videoId);

        if (!$video) {
            $this->error("Video with ID {$videoId} not found.");
            return 1;
        }

        $this->info("Enhancing video: {$video->title} (ID: {$video->id})");

        $success = $this->enhancementService->enhanceVideo($video);

        if ($success) {
            $video->refresh();
            $this->info("✅ Successfully enhanced video: {$video->title}");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Title', $video->title],
                    ['Channel', $video->channel_name],
                    ['Duration', $this->enhancementService->formatDuration($video->duration ?? 0)],
                    ['Views', number_format($video->view_count)],
                    ['Likes', number_format($video->like_count)],
                ]
            );
            return 0;
        } else {
            $this->error("❌ Failed to enhance video: {$video->title}");
            return 1;
        }
    }

    private function enhanceByYoutubeId(string $youtubeId): int
    {
        $video = Video::where('youtube_id', $youtubeId)->first();

        if (!$video) {
            $this->error("Video with YouTube ID {$youtubeId} not found.");
            return 1;
        }

        return $this->enhanceSingleVideo($video->id);
    }

    private function enhanceAllVideos(int $batchSize): int
    {
        $totalVideos = Video::count();

        if ($totalVideos === 0) {
            $this->info('No videos found to enhance.');
            return 0;
        }

        $this->info("Found {$totalVideos} videos to enhance.");

        if (!$this->confirm('Do you want to proceed?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $bar = $this->output->createProgressBar($totalVideos);
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        Video::chunk($batchSize, function ($videos) use (&$successCount, &$errorCount, $bar) {
            foreach ($videos as $video) {
                $success = $this->enhancementService->enhanceVideo($video);

                if ($success) {
                    $successCount++;
                } else {
                    $errorCount++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Enhancement completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Videos', $totalVideos],
                ['Successfully Enhanced', $successCount],
                ['Failed', $errorCount],
                ['Success Rate', round(($successCount / $totalVideos) * 100, 2) . '%'],
            ]
        );

        return $errorCount === 0 ? 0 : 1;
    }
}
