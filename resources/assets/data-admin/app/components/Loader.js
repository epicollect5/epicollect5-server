import React from 'react';

const Loader = ({ elementClass }) => {

    const loaderClass = 'loader animated fadeOut ' + (elementClass || '');
    return (
        <div className={loaderClass}>Loading...</div>
    );
};

export default Loader;
