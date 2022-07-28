import React from 'react';

const ErrorView = ({ data }) => {
    return (
        <p className="centerVH">{data.error}</p>
    );
};

export default ErrorView;
