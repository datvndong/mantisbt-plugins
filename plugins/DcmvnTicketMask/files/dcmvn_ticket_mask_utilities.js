// Copyright (c) 2025 LinkedSoft

const convertHhmmToMinutes = hhmm => {
    const [hours, minutes] = (hhmm || ':').split(':').map(Number);
    return (hours || 0) * 60 + (minutes || 0);
};

const convertMinutesToHhmm = minutes => {
    const prefix = minutes < 0 ? '-' : '';
    const hours = Math.floor(Math.abs(minutes) / 60);
    const remainingMinutes = Math.abs(minutes) % 60;

    return `${prefix}${hours.toString().padStart(2, '0')}:${remainingMinutes.toString().padStart(2, '0')}`;
};
