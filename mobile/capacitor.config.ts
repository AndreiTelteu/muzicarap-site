import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.muzicarap',
    appName: 'MuzicaRap',
    webDir: 'capacitor',
    server: {
        url: 'https://muzicarap.ro',
        cleartext: false,
    },
};

export default config;
