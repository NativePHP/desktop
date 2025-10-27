var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
import express from 'express';
import { Notification } from 'electron';
import { notifyLaravel } from "../utils.js";
import fs from 'fs';
let player;
try {
    player = require('play-sound')();
}
catch (e) {
    player = null;
}
const isLocalFile = (sound) => {
    if (typeof sound !== 'string')
        return false;
    if (/^https?:\/\//i.test(sound))
        return false;
    return sound.startsWith('/') || sound.startsWith('file:') || /^[a-zA-Z]:\\/.test(sound);
};
const normalizePath = (raw) => {
    if (raw.startsWith('file://'))
        return raw.replace(/^file:\/\//, '');
    return raw;
};
const playSound = (sound) => __awaiter(void 0, void 0, void 0, function* () {
    const filePath = normalizePath(sound);
    try {
        yield fs.promises.access(filePath, fs.constants.R_OK);
    }
    catch (err) {
        return Promise.reject(new Error(`sound file not accessible: ${filePath}`));
    }
    return new Promise((resolve, reject) => {
        if (player) {
            player.play(filePath, (err) => {
                if (err)
                    return reject(err);
                resolve();
            });
            return;
        }
        const { exec } = require('child_process');
        exec(`afplay ${JSON.stringify(filePath)}`, (err) => {
            if (err)
                return reject(err);
            resolve();
        });
    });
});
const router = express.Router();
router.post('/', (req, res) => {
    const { title, body, subtitle, silent, icon, hasReply, timeoutType, replyPlaceholder, sound, urgency, actions, closeButtonText, toastXml, event: customEvent, reference, } = req.body;
    const eventName = customEvent !== null && customEvent !== void 0 ? customEvent : '\\Native\\Desktop\\Events\\Notifications\\NotificationClicked';
    const notificationReference = reference !== null && reference !== void 0 ? reference : (Date.now() + '.' + Math.random().toString(36).slice(2, 9));
    const usingLocalFile = isLocalFile(sound);
    const createNotification = (opts) => {
        try {
            if (typeof Notification === 'function') {
                return new Notification(opts);
            }
        }
        catch (e) {
        }
        return {
            show: () => { },
            on: (_, __) => { },
        };
    };
    const notification = createNotification({
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
        playSound(sound).catch((err) => {
            notifyLaravel('events', {
                event: '\\Native\\Desktop\\Events\\Notifications\\NotificationSoundFailed',
                payload: {
                    reference: notificationReference,
                    error: String(err),
                },
            });
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
