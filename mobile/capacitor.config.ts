import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.muzicarap',
    appName: 'MuzicaRap',
    webDir: 'capacitor',
    server: {
        url: 'https://muzicarap.ro',
        cleartext: false,
    },
    android: {
        buildOptions: {
            releaseType: 'aab',
            keystorePath: './MuzicaRAP.keystore',
            keystoreAlias: 'MuzicaRAP',
            keystorePassword: 'EfWx9ZjxsUeHhtWg',
            keystoreAliasPassword: 'EfWx9ZjxsUeHhtWg',
        },
    },
};

export default config;
