import express from 'express';
import { Notification } from 'electron';
import {notifyLaravel} from "../utils.js";
import fs from 'fs';
declare const require: any;

let player: any;
try {
    player = require('play-sound')();
} catch (e) {
    player = null;
}

const isLocalFile = (sound: unknown) => {
    if (typeof sound !== 'string') return false;
    if (/^https?:\/\//i.test(sound)) return false;
    return sound.startsWith('/') || sound.startsWith('file:') || /^[a-zA-Z]:\\/.test(sound);
};

const normalizePath = (raw: string) => {
    if (raw.startsWith('file://')) return raw.replace(/^file:\/\//, '');
    return raw;
};

const playSound = async (sound: string) => {
    const filePath = normalizePath(sound);
    try {
        await fs.promises.access(filePath, fs.constants.R_OK);
    } catch (err) {
        return Promise.reject(new Error(`sound file not accessible: ${filePath}`));
    }

    return new Promise<void>((resolve, reject) => {
        if (player) {
            player.play(filePath, (err: any) => {
                if (err) return reject(err);
                resolve();
            });
            return;
        }

        const { exec } = require('child_process');
        exec(`afplay ${JSON.stringify(filePath)}`, (err: any) => {
            if (err) return reject(err);
            resolve();
        });
    });
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

    if (usingLocalFile && typeof sound === 'string') {
        playSound(sound).catch(() => {});
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
