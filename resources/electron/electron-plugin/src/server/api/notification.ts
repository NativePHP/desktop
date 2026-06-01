import { Notification } from 'electron';
import express from 'express';
import fs from 'fs';
import playSoundLib from 'play-sound';
import state from '../state.js';
import { broadcastToWindows, notifyLaravel } from '../utils.js';

const isLocalFile = (sound: unknown) => {
    if (typeof sound !== 'string') return false;
    if (/^https?:\/\//i.test(sound)) return false;
    // Treat any string containing path separators as a local file
    return sound.includes('/') || sound.includes('\\');
};
const router = express.Router();

router.post('/', (req, res) => {
    const {
        title,
        body,
        subtitle,
        silent,
        icon,
        hasReply,
        timeoutType,
        replyPlaceholder,
        sound,
        urgency,
        actions,
        closeButtonText,
        toastXml,
        event: customEvent,
        reference,
    } = req.body;

    const eventName = customEvent ?? '\\Native\\Desktop\\Events\\Notifications\\NotificationClicked';

    const notificationReference = reference ?? Date.now() + '.' + Math.random().toString(36).slice(2, 9);

    const usingLocalFile = isLocalFile(sound);

    const notification = new Notification({
        title,
        body,
        subtitle,
        silent: usingLocalFile ? true : silent,
        icon,
        hasReply,
        timeoutType,
        replyPlaceholder,
        sound: usingLocalFile ? undefined : sound,
        urgency,
        actions,
        closeButtonText,
        toastXml,
    });

    if (usingLocalFile && !silent) {
        fs.access(sound, fs.constants.F_OK, (err) => {
            if (err) {
                broadcastToWindows('log', {
                    level: 'error',
                    message: `Sound file not found: ${sound}`,
                    context: { sound },
                });
                return;
            }

            playSoundLib().play(sound, () => {});
        });
    }

    notification.on('click', (event) => {
        delete state.notifications[notificationReference];
        notifyLaravel('events', {
            event: eventName || '\\Native\\Desktop\\Events\\Notifications\\NotificationClicked',
            payload: {
                reference: notificationReference,
                event: JSON.stringify(event),
            },
        });
    });

    notification.on('action', (event, index) => {
        notifyLaravel('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationActionClicked',
            payload: {
                reference: notificationReference,
                index,
                event: JSON.stringify(event),
            },
        });
    });

    notification.on('reply', (event, reply) => {
        notifyLaravel('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationReply',
            payload: {
                reference: notificationReference,
                reply,
                event: JSON.stringify(event),
            },
        });
    });

    notification.on('close', (event) => {
        delete state.notifications[notificationReference];
        notifyLaravel('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationClosed',
            payload: {
                reference: notificationReference,
                event: JSON.stringify(event),
            },
        });
    });

    // Electron only retains a weak reference to main-process Notification
    // objects. Without a strong JS reference the wrapper can be garbage
    // collected before the user interacts with it, after which the click /
    // action / reply / close handlers silently never fire (most visible on
    // macOS when the app is idle or backgrounded). Keep it reachable until it
    // is dismissed. See https://github.com/electron/electron/issues/16922
    state.notifications[notificationReference] = notification;

    notification.show();

    res.status(200).json({
        reference: notificationReference,
    });
});

export default router;
