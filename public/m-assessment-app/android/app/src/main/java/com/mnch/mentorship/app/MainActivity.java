package com.mnch.mentorship.app;

import android.os.Bundle;
import androidx.core.view.WindowCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        // Opt out of edge-to-edge (mandatory by default on targetSdk 36 / Android 15+).
        // Without this, the WebView draws behind the status bar and the system nav
        // bar and every screen has to fight both bars via CSS safe-area insets,
        // which Android's WebView supports inconsistently across devices/OEM skins.
        // With decorFitsSystemWindows(true), Android reserves real space for both
        // bars around the WebView instead, so no CSS compensation is needed at all.
        WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
        super.onCreate(savedInstanceState);
    }
}
