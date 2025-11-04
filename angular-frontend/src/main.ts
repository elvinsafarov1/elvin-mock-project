import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { AppModule } from './app/app.module';
import { initOpenTelemetry } from './app/telemetry';

// Initialize OpenTelemetry before bootstrapping Angular
initOpenTelemetry().then(() => {
  platformBrowserDynamic()
    .bootstrapModule(AppModule)
    .catch(err => console.error(err));
});

