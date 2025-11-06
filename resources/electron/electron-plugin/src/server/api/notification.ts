import express from 'express';
import { Notification } from 'electron';
import {notifyLaravel, broadcastToWindows} from "../utils.js";
declare const require: any;
import playSoundLib from 'play-sound';
import fs from 'fs';

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

    const notificationReference = reference ?? (Date.now() + '.' + Math.random().toString(36).slice(2, 9));

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
        toastXml
    });

    if (usingLocalFile && !silent) {
        fs.access(sound, fs.constants.F_OK, (err) => {
            if (err) {
                broadcastToWindows('log', {
                    level: 'error',
                    message: `Sound file not found: ${sound}`,
                    context: { sound }
                });
                return;
            }

            playSoundLib().play(sound, () => {});
        });
    }

    notification.on("click", (event) => {
        notifyLaravel('events', {
            event: eventName || '\\Native\\Desktop\\Events\\Notifications\\NotificationClicked',
            payload: {
                reference: notificationReference,
                event: JSON.stringify(event),
            },
        });
    });

    notification.on("action", (event, index) => {
        notifyLaravel('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationActionClicked',
            payload: {
                reference: notificationReference,
                index,
                event: JSON.stringify(event),
            },
        });
    });

    notification.on("reply", (event, reply) => {
        notifyLaravel('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationReply',
            payload: {
                reference: notificationReference,
                reply,
                event: JSON.stringify(event),
            },
        });
    });

    notification.on("close", (event) => {
        notifyLaravel('events', {
            event: '\\Native\\Desktop\\Events\\Notifications\\NotificationClosed',
            payload: {
                reference: notificationReference,
                event: JSON.stringify(event),
            },
        });
    });

    notification.show();

    res.status(200).json({
        reference: notificationReference,
    });
});

export default router;
