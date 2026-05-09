type YouTubePlayerConstructor = new (
    element: HTMLElement,
    options: Record<string, unknown>,
) => YouTubePlayerInstance;

export type YouTubePlayerInstance = {
    destroy: () => void;
    getCurrentTime: () => number;
    getDuration: () => number;
    loadVideoById: (videoId: string, startSeconds?: number) => void;
    pauseVideo: () => void;
    playVideo: () => void;
    seekTo: (seconds: number, allowSeekAhead: boolean) => void;
    stopVideo: () => void;
};

type YouTubeApi = {
    Player: YouTubePlayerConstructor;
    PlayerState: {
        ENDED: number;
        PAUSED: number;
        PLAYING: number;
    };
};

let apiPromise: Promise<YouTubeApi> | null = null;

export const loadYouTubeIframeApi = (): Promise<YouTubeApi> => {
    if (typeof window === 'undefined') {
        return Promise.reject(
            new Error('YouTube API is only available in the browser.'),
        );
    }

    if (window.YT?.Player) {
        return Promise.resolve(window.YT as YouTubeApi);
    }

    if (apiPromise) {
        return apiPromise;
    }

    apiPromise = new Promise((resolve) => {
        const existingScript = document.querySelector<HTMLScriptElement>(
            'script[src="https://www.youtube.com/iframe_api"]',
        );

        const script =
            existingScript ??
            Object.assign(document.createElement('script'), {
                src: 'https://www.youtube.com/iframe_api',
            });

        window.onYouTubeIframeAPIReady = () => {
            resolve(window.YT as YouTubeApi);
        };

        if (!existingScript) {
            document.head.appendChild(script);
        }
    });

    return apiPromise;
};
