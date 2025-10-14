window.addEventListener("message", (event) => {
    if (event.data.type === "native-event") {
        const { event: eventName, payload } = event.data;

        LivewireDispatcher.handle(eventName, payload);
    }
});

const LivewireDispatcher = {
    handle: function (eventName, payload) {
        // Livewire 3
        if (window.Livewire) {
            window.Livewire.dispatch("native:" + eventName, payload);
        }

        // Livewire 2
        if (window.livewire) {
            window.livewire.components.components().forEach((component) => {
                if (Array.isArray(component.listeners)) {
                    component.listeners.forEach((event) => {
                        
                        if (event.startsWith("native")) {
                            let event_parts = event.split(
                                /(native:|native-)|:|,/,
                            );

                            if (event_parts[1] == "native:") {
                                event_parts.splice(2, 0, "private", undefined, "nativephp", undefined);
                            }

                            let [s1, signature, channel_type, s2, channel, s3, event_name] = event_parts;

                            if (eventName === event_name) {
                                // @ts-ignore
                                window.livewire.emit(event, payload);
                            }
                        }
                    });
                }
            });
        }
    },
};
